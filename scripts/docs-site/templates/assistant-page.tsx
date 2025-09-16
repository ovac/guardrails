import Layout from '@theme/Layout';
import Heading from '@theme/Heading';
import DocChat from '../components/DocChat';

export default function AssistantPage(): JSX.Element {
  return (
    <Layout title="AI Assistant" description="Chat with an OpenAI-compatible model grounded in the Guardrails docs.">
      <main className="container margin-vert--xl">
        <div className="row">
          <div className="col col--10 col--offset-1">
            <Heading as="h1">Guardrails AI Assistant</Heading>
            <p>
              Connect your OpenAI, OpenRouter, or compatible API key and get grounded answers referencing the Guardrails documentation.
              We never transmit or store your key — it stays in your browser’s local storage. Every answer includes the most relevant doc
              sections so you can verify before copying into production.
            </p>
            <DocChat />
          </div>
        </div>
      </main>
    </Layout>
  );
}
