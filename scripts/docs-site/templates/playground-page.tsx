import Layout from '@theme/Layout';
import Playground from '../components/Playground';

export default function PlaygroundPage(): JSX.Element {
  return (
    <Layout title="Playground" description="Interactive Guardrails flow builder and code generator.">
      <main className="container margin-vert--lg">
        <div className="row">
          <div className="col col--12">
            <Playground />
          </div>
        </div>
      </main>
    </Layout>
  );
}
