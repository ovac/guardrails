#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const {execSync} = require('child_process');

const repoRoot = path.resolve(__dirname, '..', '..');
const docsSourceDir = path.join(repoRoot, 'resources', 'docs');
const templatesDir = path.join(__dirname, 'templates');

const baseSidebarDefinition = {
  docs: [
    {type: 'doc', id: 'README', label: 'Welcome'},
    {
      type: 'category',
      label: 'Start Here',
      collapsed: false,
      items: ['overview', 'getting-started', 'configuration', 'faq'],
    },
    {
      type: 'category',
      label: 'Guarded Flows',
      items: [
        'usage-models',
        'usage-controllers',
        'flow-builder',
        'flows-and-policies',
        'signing-policy',
        'permissions',
        'patterns',
        'use-cases',
      ],
    },
    {
      type: 'category',
      label: 'Guides & Recipes',
      items: [
        'advanced',
        'config-recipes',
        'custom-controllers',
        'extending-models-and-migrations',
        'database',
        'external-signing',
        'verification-examples',
        'bots-and-automation',
        'organization-playbook',
        'ideas-and-examples',
        'ui',
        'voting-models',
      ],
    },
    {
      type: 'category',
      label: 'Operations & Reference',
      items: ['api', 'auditing-and-changelog', 'testing', 'testing-full'],
    },
  ],
};

function humanizeSegment(segment) {
  return segment
    .replace(/-/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (match) => match.toUpperCase());
}

function humanizeDocId(value) {
  if (!value) {
    return value;
  }

  return value
    .split('/')
    .map((segment) => humanizeSegment(segment))
    .join(' / ');
}

function extractTitleFromContent(content) {
  if (!content) {
    return null;
  }

  const normalized = content.replace(/\r\n?/g, '\n');

  const titleField = normalized.match(/^title:\s*(.+)$/m);
  if (titleField) {
    return titleField[1].trim().replace(/^['"]|['"]$/g, '');
  }

  const heading = normalized.match(/^#\s+(.+)$/m);
  if (heading) {
    return heading[1].trim();
  }

  return null;
}

function renderTemplate(filename, variables = {}) {
  const templatePath = path.join(templatesDir, filename);
  const raw = fs.readFileSync(templatePath, 'utf8');
  return raw.replace(/__([A-Z0-9_]+)__/g, (match, key) => {
    if (Object.prototype.hasOwnProperty.call(variables, key)) {
      return variables[key];
    }
    return match;
  });
}

function serializeSidebar(sidebar) {
  return JSON.stringify(sidebar, null, 2);
}

function cloneSidebar(sidebar) {
  return JSON.parse(JSON.stringify(sidebar));
}

function buildSidebarForDocs(sidebar, docIds, options = {}) {
  const clone = cloneSidebar(sidebar);
  const docSet = docIds instanceof Set ? docIds : null;
  const getTitle = typeof options.getTitle === 'function' ? options.getTitle : (id) => humanizeDocId(id);

  function filterItems(items) {
    const results = [];
    for (const item of items) {
      if (typeof item === 'string') {
        if (!docSet || docSet.has(item)) {
          results.push({type: 'doc', id: item, label: getTitle(item)});
        }
        continue;
      }

      if (!item || typeof item !== 'object') {
        continue;
      }

      if (item.type === 'doc') {
        if (item.id && (!docSet || docSet.has(item.id))) {
          const resolved = {...item};
          if (!resolved.label) {
            resolved.label = getTitle(resolved.id);
          }
          results.push(resolved);
        }
        continue;
      }

      if (item.type === 'category' && Array.isArray(item.items)) {
        const nested = filterItems(item.items);
        if (nested.length > 0) {
          results.push({...item, items: nested});
        }
        continue;
      }

      const fallback = {...item};
      if (fallback.id && !fallback.label) {
        fallback.label = getTitle(fallback.id);
      }
      results.push(fallback);
    }

    return results;
  }

  const filtered = {};
  for (const [key, value] of Object.entries(clone)) {
    if (Array.isArray(value)) {
      filtered[key] = filterItems(value);
    } else {
      filtered[key] = value;
    }
  }

  return filtered;
}


if (!fs.existsSync(docsSourceDir)) {
  console.error('Unable to locate resources/docs directory.');
  process.exit(1);
}

const outDirArg = process.argv[2];
const outDir = path.resolve(repoRoot, outDirArg || 'build/docs-site');

function ensureDir(targetPath) {
  fs.mkdirSync(targetPath, {recursive: true});
}

function run(command, options = {}) {
  execSync(command, {
    stdio: options.stdio || 'inherit',
    cwd: options.cwd || repoRoot,
    env: {...process.env, ...(options.env || {})},
  });
}

function runCapture(command) {
  try {
    return execSync(command, {cwd: repoRoot, stdio: ['ignore', 'pipe', 'pipe']}).toString().trim();
  } catch (error) {
    return '';
  }
}

function writeFile(filePath, contents) {
  ensureDir(path.dirname(filePath));
  fs.writeFileSync(filePath, contents);
}

function copyRecursive(src, dest) {
  const stat = fs.statSync(src);
  if (stat.isDirectory()) {
    ensureDir(dest);
    for (const entry of fs.readdirSync(src)) {
      copyRecursive(path.join(src, entry), path.join(dest, entry));
    }
  } else {
    ensureDir(path.dirname(dest));
    fs.copyFileSync(src, dest);
  }
}

function sanitizeAnglesAndLinks(filePath) {
  const raw = fs.readFileSync(filePath, 'utf8');
  let updated = raw.replace(/<(\d)/g, '&lt;$1');
  updated = updated.replace(/resources\/docs\//g, './');
  updated = updated.replace(/(?<!\\)\{([a-zA-Z0-9_]+)\}/g, (_match, key) => `\\{${key}\\}`);
  if (updated !== raw) {
    fs.writeFileSync(filePath, updated);
  }
}

function sanitizeDocs(directory) {
  if (!fs.existsSync(directory)) {
    return;
  }
  const entries = fs.readdirSync(directory, {withFileTypes: true});
  for (const entry of entries) {
    const currentPath = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      sanitizeDocs(currentPath);
    } else if (/\.mdx?$/.test(entry.name)) {
      sanitizeAnglesAndLinks(currentPath);
    }
  }
}

function normalizeBaseUrl(value, fallback = '/') {
  let base = value || fallback;
  if (!base.startsWith('/')) {
    base = `/${base}`;
  }
  if (!base.endsWith('/')) {
    base = `${base}/`;
  }
  return base;
}

function slugify(value) {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-');
}

function trimContent(value) {
  return value
    .replace(/```[\s\S]*?```/g, '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 1200);
}

function collectDocIdsFromPaths(paths) {
  const ids = new Set();
  paths.forEach((relativePath) => {
    const normalized = relativePath.replace(/\\/g, '/');
    const id = normalized.replace(/\.(mdx?)$/i, '');
    ids.add(id);
  });
  return ids;
}

function gatherDocInfo(rootDir) {
  const relativePaths = [];
  const titleMap = new Map();

  function walk(currentDir) {
    for (const entry of fs.readdirSync(currentDir, {withFileTypes: true})) {
      const fullPath = path.join(currentDir, entry.name);
      if (entry.isDirectory()) {
        walk(fullPath);
        continue;
      }

      if (!/\.mdx?$/i.test(entry.name)) {
        continue;
      }

      const relative = path.relative(rootDir, fullPath).replace(/\\/g, '/');
      relativePaths.push(relative);
      const id = relative.replace(/\.(mdx?)$/i, '');
      try {
        const raw = fs.readFileSync(fullPath, 'utf8');
        const title = extractTitleFromContent(raw);
        if (title) {
          titleMap.set(id, title);
        }
      } catch (error) {
        // Ignore unreadable files; fallback titles will be generated later.
      }
    }
  }

  walk(rootDir);

  return {
    relativePaths,
    titles: titleMap,
  };
}

function collectSections(rootDir) {
  const sections = [];

  function parseFile(filePath) {
    const raw = fs.readFileSync(filePath, 'utf8');
    const withoutFrontmatter = raw.replace(/^---[\s\S]*?---\s*/m, '');
    const lines = withoutFrontmatter.split(/\r?\n/);
    const relative = path.relative(rootDir, filePath).replace(/\\/g, '/');
    const baseSlug = `docs/${relative.replace(/\.mdx?$/, '')}`;

    let currentTitle = path.basename(relative, path.extname(relative));
    let buffer = [];

    function pushSection() {
      if (!buffer.length) {
        return;
      }
      const content = trimContent(buffer.join('\n'));
      if (!content) {
        buffer = [];
        return;
      }
      sections.push({
        id: `${baseSlug}#${slugify(currentTitle)}`,
        title: currentTitle,
        content,
        href: `/${baseSlug}`,
      });
      buffer = [];
    }

    for (const line of lines) {
      const headingMatch = line.match(/^#{1,5}\s+(.*)$/);
      if (headingMatch) {
        pushSection();
        currentTitle = headingMatch[1].trim();
      } else {
        buffer.push(line);
      }
    }

    pushSection();
  }

  function walk(currentDir) {
    for (const entry of fs.readdirSync(currentDir, {withFileTypes: true})) {
      const fullPath = path.join(currentDir, entry.name);
      if (entry.isDirectory()) {
        walk(fullPath);
      } else if (/\.mdx?$/.test(entry.name)) {
        parseFile(fullPath);
      }
    }
  }

  walk(rootDir);
  return sections;
}

function compareVersions(a, b) {
  const toParts = (value) => value.split('.').map((segment) => parseInt(segment, 10) || 0);
  const [a1, a2 = 0, a3 = 0] = toParts(a);
  const [b1, b2 = 0, b3 = 0] = toParts(b);
  if (a1 !== b1) return b1 - a1;
  if (a2 !== b2) return b2 - a2;
  return b3 - a3;
}

function prepareVersionedDocs(siteDir) {
  let tags = runCapture("git tag --list 'v*'");
  if (!tags) {
    return [];
  }

  const versions = tags
    .split('\n')
    .map((tag) => tag.trim())
    .filter(Boolean)
    .map((tag) => tag.replace(/^v/, ''))
    .sort(compareVersions);

  const versionedDocsDir = path.join(siteDir, 'versioned_docs');
  const versionedSidebarsDir = path.join(siteDir, 'versioned_sidebars');
  ensureDir(versionedDocsDir);
  ensureDir(versionedSidebarsDir);

  versions.forEach((version) => {
    const tag = `v${version}`;
    const listing = runCapture(`git ls-tree -r --name-only ${tag} resources/docs`);
    if (!listing) {
      return;
    }
    const docs = listing.split('\n').map((item) => item.trim()).filter(Boolean);
    const destinationDir = path.join(versionedDocsDir, `version-${version}`);
    ensureDir(destinationDir);
    const relativePaths = [];
    const titleMap = new Map();
    docs.forEach((docPath) => {
      const contents = runCapture(`git show ${tag}:${docPath}`);
      const relative = docPath.replace('resources/docs/', '');
      const outputPath = path.join(destinationDir, relative);
      writeFile(outputPath, contents);
      relativePaths.push(relative);
      const id = relative.replace(/\.(mdx?)$/i, '');
      const title = extractTitleFromContent(contents) || humanizeDocId(id);
      titleMap.set(id, title);
    });
    const sidebarPath = path.join(versionedSidebarsDir, `version-${version}-sidebars.json`);
    const docIds = collectDocIdsFromPaths(relativePaths);
    const versionedSidebar = buildSidebarForDocs(baseSidebarDefinition, docIds, {
      getTitle: (id) => titleMap.get(id) || humanizeDocId(id),
    });
    writeFile(sidebarPath, serializeSidebar(versionedSidebar));
  });

  writeFile(path.join(siteDir, 'versions.json'), JSON.stringify(versions, null, 2));
  return versions;
}

function buildDocsIndex(siteDir) {
  const sections = collectSections(docsSourceDir);
  const output = path.join(siteDir, 'static', 'docs-index.json');
  writeFile(output, JSON.stringify(sections, null, 2));
}

function createSiteStructure(siteDir, options) {
  const {
    siteUrl,
    baseUrl,
    organizationName,
    projectName,
    repoUrl,
    packagistUrl,
    canonicalUrl,
    sidebarDefinition,
    versions = [],
  } = options;

  const currentYear = new Date().getFullYear().toString();
  const hasVersions = Array.isArray(versions) && versions.length > 0;

  const replacements = {
    SITE_URL: siteUrl,
    BASE_URL: baseUrl,
    ORGANIZATION_NAME: organizationName,
    PROJECT_NAME: projectName,
    REPO_URL: repoUrl,
    PACKAGIST_URL: packagistUrl,
    CANONICAL_URL: canonicalUrl,
    CURRENT_YEAR: currentYear,
    NAVBAR_VERSION_ITEM: hasVersions
      ? `        {\n          type: 'docsVersionDropdown',\n          position: 'right',\n          dropdownActiveClassDisabled: true,\n        },\n`
      : '',
    DOCS_VERSION_CONFIG: hasVersions
      ? `          versions: {\n            current: {\n              label: 'Next',\n            },\n          },\n          lastVersion: '${versions[0]}',\n`
      : '',
  };

  const files = new Map([
    ['package.json', 'package.json'],
    ['tsconfig.json', 'tsconfig.json'],
    ['docusaurus.config.ts', 'docusaurus.config.ts'],
    ['sidebars.ts', 'sidebars.ts'],
    ['custom.css', 'src/css/custom.css'],
    ['index.module.css', 'src/pages/index.module.css'],
    ['home.tsx', 'src/pages/index.tsx'],
    ['playground.tsx', 'src/components/Playground.tsx'],
    ['doc-chat.tsx', 'src/components/DocChat.tsx'],
    ['playground-page.tsx', 'src/pages/playground.tsx'],
    ['assistant-page.tsx', 'src/pages/assistant.tsx'],
    ['logo.svg', 'static/img/logo.svg'],
    ['favicon.svg', 'static/img/favicon.svg'],
    ['social-card.svg', 'static/img/social-card.svg'],
  ]);

  for (const [templateName, outputPath] of files.entries()) {
    const extras = templateName === 'sidebars.ts'
      ? {SIDEBAR_JSON: serializeSidebar(sidebarDefinition || baseSidebarDefinition)}
      : {};
    const contents = renderTemplate(templateName, {...replacements, ...extras});
    writeFile(path.join(siteDir, outputPath), contents);
  }
}

function main() {
  const repository = process.env.GITHUB_REPOSITORY || 'ovac/guardrails';
  const [organizationName = 'ovac', projectName = 'guardrails'] = repository.split('/');

  const defaultSiteUrl = `https://${organizationName}.github.io`;
  const siteUrl = (process.env.DOCS_SITE_URL || defaultSiteUrl).replace(/\/$/, '');

  let baseUrl = process.env.DOCS_BASE_URL;
  if (!baseUrl) {
    if (projectName === `${organizationName}.github.io`) {
      baseUrl = '/';
    } else {
      baseUrl = `/${projectName}/`;
    }
  }
  baseUrl = normalizeBaseUrl(baseUrl);

  const canonicalUrl = `${siteUrl}${baseUrl}`;
  const repoUrl = process.env.DOCS_REPOSITORY_URL || `https://github.com/${repository}`;
  const packagistUrl = process.env.DOCS_PACKAGIST_URL || 'https://packagist.org/packages/ovac/guardrails';

  const tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'guardrails-docs-'));
  const siteDir = path.join(tmpDir, 'site');
  ensureDir(siteDir);

  const currentDocsInfo = gatherDocInfo(docsSourceDir);
  const currentDocIds = collectDocIdsFromPaths(currentDocsInfo.relativePaths);
  const sidebarForCurrent = buildSidebarForDocs(baseSidebarDefinition, currentDocIds, {
    getTitle: (id) => currentDocsInfo.titles.get(id) || humanizeDocId(id),
  });

  // Copy docs into site
  copyRecursive(docsSourceDir, path.join(siteDir, 'docs'));
  sanitizeDocs(path.join(siteDir, 'docs'));

  // Prepare docs index
  buildDocsIndex(siteDir);

  // Prepare versioned docs from tags
  const versions = prepareVersionedDocs(siteDir);
  sanitizeDocs(path.join(siteDir, 'versioned_docs'));

  createSiteStructure(siteDir, {
    siteUrl,
    baseUrl,
    organizationName,
    projectName,
    repoUrl,
    packagistUrl,
    canonicalUrl,
    sidebarDefinition: sidebarForCurrent,
    versions,
  });

  // Run npm install/build inside the generated site
  run('npm install --no-audit --no-fund', {cwd: siteDir});
  run('npm run build', {cwd: siteDir});

  const buildDir = path.join(siteDir, 'build');
  if (!fs.existsSync(buildDir)) {
    console.error('Docusaurus build failed: build directory missing.');
    process.exit(1);
  }

  if (fs.existsSync(outDir)) {
    fs.rmSync(outDir, {recursive: true, force: true});
  }
  ensureDir(path.dirname(outDir));
  copyRecursive(buildDir, outDir);

  // Write metadata file with versions for reference in deployment artifacts
  const meta = {
    generatedAt: new Date().toISOString(),
    siteUrl,
    baseUrl,
    canonicalUrl,
    repository,
    versions,
  };
  writeFile(path.join(outDir, '.docs-site-meta.json'), `${JSON.stringify(meta, null, 2)}\n`);

  fs.rmSync(tmpDir, {recursive: true, force: true});
}

main();
