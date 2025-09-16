import type {Config} from '@docusaurus/types';
import github from 'prism-react-renderer/themes/github';
import dracula from 'prism-react-renderer/themes/dracula';

const config: Config = {
  title: 'Guardrails',
  tagline: 'Human-in-the-loop approvals for Laravel with zero lock-in.',
  favicon: 'img/favicon.svg',
  url: '__SITE_URL__',
  baseUrl: '__BASE_URL__',
  organizationName: '__ORGANIZATION_NAME__',
  projectName: '__PROJECT_NAME__',
  onBrokenLinks: 'throw',
  onBrokenMarkdownLinks: 'warn',
  trailingSlash: false,
  deploymentBranch: 'gh-pages',
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },
  customFields: {
    repoUrl: '__REPO_URL__',
    packagistUrl: '__PACKAGIST_URL__',
  },
  presets: [
    [
      'classic',
      {
        docs: {
          path: 'docs',
          routeBasePath: 'docs',
          sidebarPath: './sidebars.ts',
          editUrl: '__REPO_URL__/edit/main/resources/docs/',
          showLastUpdateTime: false,
          showLastUpdateAuthor: false,
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
        sitemap: {
          changefreq: 'weekly',
          priority: 0.6,
        },
      },
    ],
  ],
  plugins: [
    [
      '@docusaurus/plugin-client-redirects',
      {
        redirects: [
          {
            to: '/docs/overview',
            from: ['/docs'],
          },
        ],
      },
    ],
  ],
  themeConfig: {
    metadata: [
      {name: 'keywords', content: 'Laravel approvals, guardrails, human in the loop, approval workflows'},
      {name: 'author', content: 'Ovac'},
      {name: 'twitter:card', content: 'summary_large_image'},
      {name: 'twitter:site', content: '@ovac4u'},
    ],
    image: 'img/social-card.svg',
    navbar: {
      title: 'Guardrails',
      logo: {
        alt: 'Guardrails logo',
        src: 'img/logo.svg',
      },
      items: [
        {to: '/docs/overview', label: 'Docs', position: 'left'},
        {to: '/playground', label: 'Playground', position: 'left'},
        {to: '/assistant', label: 'AI Assistant', position: 'left'},
        {
          href: '__REPO_URL__',
          label: 'GitHub',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Docs',
          items: [
            {label: 'Overview', to: '/docs/overview'},
            {label: 'Configuration', to: '/docs/configuration'},
            {label: 'API', to: '/docs/api'},
          ],
        },
        {
          title: 'Project',
          items: [
            {label: 'GitHub', href: '__REPO_URL__'},
            {
              label: 'Issues',
              href: '__REPO_URL__/issues',
            },
            {label: 'Packagist', href: '__PACKAGIST_URL__'},
          ],
        },
        {
          title: 'Stay Updated',
          items: [
            {label: 'Changelog', to: '/docs/overview'},
            {label: 'Blog (coming soon)', to: '/docs/getting-started'},
            {label: 'Twitter', href: 'https://twitter.com/ovac4u'},
          ],
        },
      ],
      copyright: 'Copyright © __CURRENT_YEAR__ Guardrails contributors. Built with Docusaurus.',
    },
    prism: {
      theme: github,
      darkTheme: dracula,
      additionalLanguages: ['php', 'bash'],
    },
    colorMode: {
      defaultMode: 'light',
      respectPrefersColorScheme: true,
    },
    docs: {
      sidebar: {
        hideable: true,
      },
    },
    announcementBar: {
      id: 'guardrails-docs-ga',
      content: '⭐️ Star <a href="__REPO_URL__" target="_blank" rel="noreferrer">ovac/guardrails</a> to receive release updates.',
      isCloseable: true,
    },
  },
  headTags: [
    {
      tagName: 'link',
      attributes: {rel: 'preconnect', href: 'https://api.openai.com'},
    },
    {
      tagName: 'link',
      attributes: {rel: 'canonical', href: '__CANONICAL_URL__'},
    },
  ],
};

export default config;
