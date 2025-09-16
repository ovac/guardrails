import Layout from '@theme/Layout';
import Link from '@docusaurus/Link';
import Heading from '@theme/Heading';
import clsx from 'clsx';
import styles from './index.module.css';

const featureList = [
  {
    title: 'Opinionated Docs. Zero Drift.',
    description:
      'All markdown under resources/docs is published automatically with navigation powered by Docusaurus and editable links tied to GitHub.',
  },
  {
    title: 'Interactive Playground',
    description:
      'Prototype guard flows, approval paths, and generate copy-paste PHP snippets without leaving the docs – ideal for demos and onboarding.',
  },
  {
    title: 'AI Co-pilot',
    description:
      'Bring your model API key and chat against the curated Guardrails knowledge base to draft code, migrations, and approval policies in seconds.',
  },
  {
    title: 'Versioned and SEO Friendly',
    description:
      'Each release can publish frozen docs with sitemap metadata, canonical URLs, and GitHub Pages friendly URLs for long-term discoverability.',
  },
];

function Feature({title, description}: {title: string; description: string}) {
  return (
    <div className={clsx('col col--6 margin-bottom--lg')}>
      <div className="playgroundCard">
        <Heading as="h3">{title}</Heading>
        <p>{description}</p>
      </div>
    </div>
  );
}

export default function Home(): JSX.Element {
  return (
    <Layout
      title="Guardrails documentation"
      description="Human-in-the-loop approvals for Laravel with interactive docs, playground, and AI co-pilot."
    >
      <header className={clsx('hero hero--primary', styles.heroBanner)}>
        <div className="container">
          <Heading as="h1" className={styles.heroTitle}>
            Guardrails Documentation Hub
          </Heading>
          <p className={styles.heroSubtitle}>
            Explore secure approval flows, tweak interactive playgrounds, and let the AI chat craft implementation-ready snippets for your team.
          </p>
          <div className={styles.heroButtons}>
            <Link className="button button--secondary button--lg" to="/docs/overview">
              Dive into the Docs
            </Link>
            <Link className="button button--outline button--lg" to="/playground">
              Build a Flow
            </Link>
            <Link className="button button--primary button--lg" to="/assistant">
              Ask the AI
            </Link>
          </div>
        </div>
      </header>
      <main>
        <section className="section--alt">
          <div className="container">
            <div className="row">
              {featureList.map((feature) => (
                <Feature key={feature.title} title={feature.title} description={feature.description} />
              ))}
            </div>
          </div>
        </section>
        <section>
          <div className="container">
            <div className="row">
              <div className="col">
                <Heading as="h2">In the workflow? Guardrails has you covered.</Heading>
                <p>
                  The docs ship with searchable navigation, generated sitemap metadata, and GitHub-powered edits. Every push to main
                  re-publishes the static site via GitHub Pages so your docs never drift away from the code. Versioned snapshots make
                  onboarding easy for long-lived customer deployments.
                </p>
                <p>
                  Want a tailored code sample? Hop into the AI assistant with your preferred model key — it automatically stitches
                  together the best-matching snippets from the docs before calling the API, so answers stay grounded in Guardrails usage.
                </p>
              </div>
            </div>
          </div>
        </section>
      </main>
    </Layout>
  );
}
