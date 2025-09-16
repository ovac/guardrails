import Layout from '@theme/Layout';
import Heading from '@theme/Heading';
import Playground from '../components/Playground';

export default function PlaygroundPage(): JSX.Element {
  return (
    <Layout title="Playground" description="Interactive Guardrails flow builder and code generator.">
      <main className="container margin-vert--xl">
        <div className="row">
          <div className="col col--10 col--offset-1">
            <Heading as="h1">Guardrails Playground</Heading>
            <p>
              Prototype flows without editing code. Tweak the parameters below to generate PHP snippets you can drop directly into your
              models or controllers. The Playground keeps everything client side so you can experiment freely.
            </p>
            <Playground />
          </div>
        </div>
      </main>
    </Layout>
  );
}
