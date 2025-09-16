import React, {useMemo, useState} from 'react';

const templates = {
  twoPerson: {
    label: 'Two-person rule for publishing',
    description: 'Author plus one peer for publishing a post.',
    defaults: {
      includeInitiator: true,
      approvalsRequired: 2,
      permissions: 'content.publish',
      roles: '',
      stepName: 'Editorial Review',
      guardAttribute: 'published',
    },
  },
  escalation: {
    label: 'Escalating approvals across teams',
    description: 'Support lead then security officer for destructive actions.',
    defaults: {
      includeInitiator: false,
      approvalsRequired: 1,
      permissions: '',
      roles: 'support_lead,security_officer',
      stepName: 'Escalated Approval',
      guardAttribute: 'status',
    },
  },
  anyOf: {
    label: 'Flexible any-of roles',
    description: 'Allow one of many operations managers to approve refunds.',
    defaults: {
      includeInitiator: false,
      approvalsRequired: 1,
      permissions: '',
      roles: 'finance_manager,ops_manager',
      stepName: 'Management Approval',
      guardAttribute: 'refunded',
    },
  },
};

type TemplateKey = keyof typeof templates;

type FlowConfig = {
  includeInitiator: boolean;
  approvalsRequired: number;
  permissions: string;
  roles: string;
  stepName: string;
  guardAttribute: string;
};

function buildSnippet(values: FlowConfig, templateKey: TemplateKey): string {
  const permissionLines = values.permissions
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
  const roleLines = values.roles
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);

  const builderLines = [
    'Flow::make()',
    permissionLines.length
      ? `    ->anyOfPermissions([${permissionLines.map((item) => `'${item}'`).join(', ')}])`
      : null,
    roleLines.length
      ? `    ->anyOfRoles([${roleLines.map((item) => `'${item}'`).join(', ')}])`
      : null,
    values.includeInitiator
      ? '    ->includeInitiator(true, true)'
      : null,
    `    ->toStep(${values.approvalsRequired}, '${values.stepName}')`,
    '    ->build();',
  ].filter(Boolean);

  if (templateKey === 'escalation' && roleLines.length >= 2) {
    const [firstRole, secondRole] = roleLines;
    builderLines.splice(1, 0, `    ->anyOfRoles(['${firstRole}'])`);
    builderLines.splice(3, 0, `    ->toStep(1, '${values.stepName} - Level 1')`);
    builderLines.splice(4, 0, `    ->anyOfRoles(['${secondRole}'])`);
    builderLines.splice(5, 0, `    ->toStep(1, '${values.stepName} - Level 2')`);
    builderLines.splice(builderLines.indexOf('    ->build();'), 0, '    // append more ->anyOfRoles()->toStep() as needed');
  }

  return `use OVAC\\Guardrails\\Concerns\\ActorGuarded;\nuse OVAC\\Guardrails\\Services\\Flow;\n\nclass ExampleModel extends Model\n{\n    use ActorGuarded;\n\n    protected array \$guarded = ['id'];\n\n    public function humanGuardAttributes(): array\n    {\n        return ['${values.guardAttribute}'];\n    }\n\n    public function actorApprovalFlow(array \$dirty, string \$event): array\n    {\n        return [\n${builderLines.map((line) => `            ${line}`).join('\n')}\n        ];\n    }\n}\n`;
}

export default function Playground(): JSX.Element {
  const [template, setTemplate] = useState<TemplateKey>('twoPerson');
  const [values, setValues] = useState<FlowConfig>(templates.twoPerson.defaults);
  const [copied, setCopied] = useState(false);

  const snippet = useMemo(() => buildSnippet(values, template), [values, template]);

  function handleChange<K extends keyof FlowConfig>(key: K, newValue: FlowConfig[K]) {
    setValues((prev) => ({...prev, [key]: newValue}));
  }

  function handleTemplateChange(event: React.ChangeEvent<HTMLSelectElement>) {
    const nextTemplate = event.target.value as TemplateKey;
    setTemplate(nextTemplate);
    setValues(templates[nextTemplate].defaults);
  }

  async function copyToClipboard() {
    await navigator.clipboard.writeText(snippet);
    setCopied(true);
    setTimeout(() => setCopied(false), 1600);
  }

  return (
    <div className="playgroundCard">
      <div>
        <label htmlFor="template">Scenario</label>
        <select
          id="template"
          className="margin-top--xs"
          value={template}
          onChange={handleTemplateChange}
        >
          {Object.entries(templates).map(([key, meta]) => (
            <option key={key} value={key}>
              {meta.label}
            </option>
          ))}
        </select>
        <p className="margin-top--sm">{templates[template].description}</p>
      </div>
      <div className="row">
        <div className="col col--6">
          <label htmlFor="permissions">Permissions (comma separated)</label>
          <input
            id="permissions"
            type="text"
            value={values.permissions}
            onChange={(event) => handleChange('permissions', event.target.value)}
          />
        </div>
        <div className="col col--6">
          <label htmlFor="roles">Roles (comma separated)</label>
          <input id="roles" type="text" value={values.roles} onChange={(event) => handleChange('roles', event.target.value)} />
        </div>
      </div>
      <div className="row">
        <div className="col col--6">
          <label htmlFor="approvalsRequired">Approvals required in step</label>
          <input
            id="approvalsRequired"
            type="number"
            min={1}
            max={5}
            value={values.approvalsRequired}
            onChange={(event) => handleChange('approvalsRequired', Number(event.target.value))}
          />
        </div>
        <div className="col col--6">
          <label htmlFor="stepName">Step label</label>
          <input id="stepName" type="text" value={values.stepName} onChange={(event) => handleChange('stepName', event.target.value)} />
        </div>
      </div>
      <div className="row">
        <div className="col col--6">
          <label htmlFor="guardAttribute">Guarded attribute</label>
          <input
            id="guardAttribute"
            type="text"
            value={values.guardAttribute}
            onChange={(event) => handleChange('guardAttribute', event.target.value)}
          />
        </div>
        <div className="col col--6" style={{display: 'flex', alignItems: 'flex-end', gap: '0.5rem'}}>
          <label style={{display: 'flex', alignItems: 'center', gap: '0.5rem'}}>
            <input
              type="checkbox"
              checked={values.includeInitiator}
              onChange={(event) => handleChange('includeInitiator', event.target.checked)}
            />
            Count initiator as signer
          </label>
        </div>
      </div>
      <div>
        <button className="button button--primary" type="button" onClick={copyToClipboard}>
          {copied ? 'Copied!' : 'Copy snippet'}
        </button>
      </div>
      <pre>
        <code>{snippet}</code>
      </pre>
    </div>
  );
}
