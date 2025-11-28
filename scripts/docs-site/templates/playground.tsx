import React, {useEffect, useMemo, useRef, useState} from 'react';
import {useDocsVersionCandidates} from '@docusaurus/plugin-content-docs/client';
import {useColorMode} from '@docusaurus/theme-common';
import {Prism as SyntaxHighlighter} from 'react-syntax-highlighter';
import dracula from 'react-syntax-highlighter/dist/esm/styles/prism/dracula';
import oneLight from 'react-syntax-highlighter/dist/esm/styles/prism/one-light';

type Scenario = 'model' | 'controller';
type OutputMode = 'flow' | 'config' | 'controller';

type StepMode = 'any' | 'all';

type FlowStep = {
  label: string;
  approvals: number;
  permissions: string;
  permissionsMode: StepMode;
  roles: string;
  rolesMode: StepMode;
  samePermissionAsInitiator: boolean;
  sameRoleAsInitiator: boolean;
  minRejections: number | null;
  maxRejections: number | null;
};

type FlowConfig = {
  includeInitiator: boolean;
  steps: FlowStep[];
  fields: string;
  scenario: Scenario;
  outputMode?: OutputMode;
  configKey?: string;
};

type TemplateMeta = {
  label: string;
  description: string;
  defaults: FlowConfig;
  controller: {
    description: string;
    fields: string;
  };
};

function createStep(overrides: Partial<FlowStep>): FlowStep {
  return {
    label: 'Approval Step',
    approvals: 1,
    permissions: '',
    permissionsMode: 'any',
    roles: '',
    rolesMode: 'any',
    samePermissionAsInitiator: false,
    sameRoleAsInitiator: false,
    minRejections: null,
    maxRejections: null,
    ...overrides,
  };
}

const templatePresets = {
  twoPerson: {
    label: 'Two-person publish rule',
    description: 'Author plus one peer required before publishing a post.',
    defaults: {
      includeInitiator: true,
      steps: [
        createStep({
          label: 'Editorial Review',
          approvals: 2,
          permissions: 'content.publish',
          permissionsMode: 'all',
        }),
      ],
      fields: 'published',
      scenario: 'model',
    },
    controller: {
      description: 'Controller intercept requiring initiator + one editor before toggling publish flag.',
      fields: 'published',
    },
  },
  escalation: {
    label: 'Ops escalates to security',
    description: 'Ops lead approves first, then security for destructive actions.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Ops Review', roles: 'ops_lead', rolesMode: 'any'}),
        createStep({label: 'Security Review', roles: 'security_officer', rolesMode: 'any'}),
      ],
      fields: 'status',
      scenario: 'model',
    },
    controller: {
      description: 'Controller flow intercepting destructive operations, first ops then security.',
      fields: 'status',
    },
  },
  anyOf: {
    label: 'Management any-of approval',
    description: 'One of many finance or ops managers can approve refunds.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({
          label: 'Management Approval',
          roles: 'finance_manager,ops_manager',
          rolesMode: 'any',
        }),
      ],
      fields: 'refunded',
      scenario: 'model',
    },
    controller: {
      description: 'Controller hook requiring one manager before finalizing refund payload.',
      fields: 'refunded',
    },
  },
  policy: {
    label: 'Policy update - legal or security',
    description: 'Any one of legal or security can approve a policy change.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({
          label: 'Policy Review',
          roles: 'legal_lead,security_lead',
          rolesMode: 'any',
        }),
      ],
      fields: 'policy_text',
      scenario: 'model',
    },
    controller: {
      description: 'Pass inbound policy payload through approval before persisting.',
      fields: 'policy_text',
    },
  },
  highRiskConfig: {
    label: 'High-risk config change',
    description: 'Peer confirmation required when toggling high-risk configuration flags.',
    defaults: {
      includeInitiator: true,
      steps: [
        createStep({
          label: 'Config Review',
          approvals: 2,
          permissions: 'infra.config',
          permissionsMode: 'all',
          roles: 'senior_engineer,team_lead',
          rolesMode: 'all',
        }),
      ],
      fields: 'high_risk_flags',
      scenario: 'model',
    },
    controller: {
      description: 'Intercept config payload to require second engineer confirmation.',
      fields: 'high_risk_flags',
    },
  },
  financial: {
    label: 'Payout double-sign',
    description: 'Two approvals with payouts.approve permission before releasing payouts.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({
          label: 'Finance Sign-off',
          approvals: 2,
          permissions: 'payouts.approve',
          permissionsMode: 'all',
        }),
      ],
      fields: 'payout_status',
      scenario: 'model',
    },
    controller: {
      description: 'Require two finance approvers before marking payout as released.',
      fields: 'payout_status',
    },
  },
  piiAccess: {
    label: 'PII access change',
    description: 'Security and data-protection officer must approve PII access changes.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Security Approval', roles: 'security_officer', rolesMode: 'any'}),
        createStep({label: 'Privacy Review', roles: 'data_protection_officer', rolesMode: 'any'}),
      ],
      fields: 'pii_access_level',
      scenario: 'model',
    },
    controller: {
      description: 'Intercept API to adjust PII access; requires both security and DPO.',
      fields: 'pii_access_level',
    },
  },
  anyOfRoles: {
    label: 'Any-of roles with initiator optional',
    description: 'Allow one of several operations leads, optionally counting the initiator when eligible.',
    defaults: {
      includeInitiator: true,
      steps: [
        createStep({
          label: 'Operations Review',
          approvals: 2,
          roles: 'operations_lead,regional_lead',
          rolesMode: 'any',
        }),
      ],
      fields: 'status',
      scenario: 'model',
    },
    controller: {
      description: 'Controller intercept for operations review on critical changes.',
      fields: 'status',
    },
  },
  escalationLevels: {
    label: 'Multi-level escalation',
    description: 'Three escalation tiers (ops → legal → executive) for irreversible changes.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Ops Escalation', roles: 'ops_lead', rolesMode: 'any'}),
        createStep({label: 'Legal Escalation', roles: 'legal_lead', rolesMode: 'any'}),
        createStep({label: 'Executive Escalation', roles: 'executive', rolesMode: 'any'}),
      ],
      fields: 'state',
      scenario: 'model',
    },
    controller: {
      description: 'Intercept irreversible controller action for three-step escalation.',
      fields: 'state',
    },
  },
  peerConfirmation: {
    label: 'Peer confirmation (same permission)',
    description: 'Initiator counted only if another peer shares the permission.',
    defaults: {
      includeInitiator: true,
      steps: [
        createStep({
          label: 'Peer Confirmation',
          approvals: 2,
          permissions: 'local_rates.manage',
          permissionsMode: 'any',
          samePermissionAsInitiator: true,
        }),
      ],
      fields: 'local_rates',
      scenario: 'model',
    },
    controller: {
      description: 'Controller-level guard requiring peer with same permission for change.',
      fields: 'local_rates',
    },
  },
  vendorOnboarding: {
    label: 'Vendor onboarding compliance',
    description: 'Procurement, security, and finance approvals before activating a new vendor.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Procurement Review', roles: 'procurement_lead', rolesMode: 'any'}),
        createStep({label: 'Security Assessment', roles: 'security_analyst', rolesMode: 'any'}),
        createStep({label: 'Finance Approval', roles: 'finance_controller', rolesMode: 'any'}),
      ],
      fields: 'vendor_status,contract_terms',
      scenario: 'model',
    },
    controller: {
      description: 'Controller guard to route vendor onboarding through compliance before activation.',
      fields: 'vendor_status,contract_terms',
    },
  },
  emergencyKillSwitch: {
    label: 'Emergency kill switch',
    description: 'Sequential SRE, incident, and executive approvals before disabling production.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'SRE Approval', roles: 'sre_on_call', rolesMode: 'any'}),
        createStep({label: 'Incident Commander Confirmation', roles: 'incident_commander', rolesMode: 'any'}),
        createStep({label: 'Executive Override', roles: 'chief_technology_officer', rolesMode: 'any'}),
      ],
      fields: 'kill_switch',
      scenario: 'controller',
    },
    controller: {
      description: 'Require human approvals before toggling the production kill switch.',
      fields: 'kill_switch',
    },
  },
  regionalPricing: {
    label: 'Regional pricing override',
    description: 'Regional manager and finance must approve strategic price overrides.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Regional Review', roles: 'regional_manager', rolesMode: 'any'}),
        createStep({label: 'Finance Sign-off', roles: 'finance_director', rolesMode: 'any'}),
      ],
      fields: 'regional_pricing',
      scenario: 'model',
    },
    controller: {
      description: 'Intercept price override payloads until regional and finance stakeholders approve.',
      fields: 'regional_pricing,notes',
    },
  },
  contractAmendment: {
    label: 'Enterprise contract amendment',
    description: 'Account executive, legal, and CFO approvals for contract changes.',
    defaults: {
      includeInitiator: true,
      steps: [
        createStep({label: 'Account Executive Confirmation', roles: 'account_executive', rolesMode: 'any'}),
        createStep({label: 'Legal Review', roles: 'legal_counsel', rolesMode: 'any'}),
        createStep({label: 'CFO Sign-off', roles: 'chief_financial_officer', rolesMode: 'any'}),
      ],
      fields: 'contract_terms,contract_value',
      scenario: 'controller',
    },
    controller: {
      description: 'Require layered sign-off before applying amendments to enterprise contracts.',
      fields: 'contract_terms,contract_value',
    },
  },
  budgetIncrease: {
    label: 'Departmental budget increase',
    description: 'Ops, finance partner, and CFO approvals for expanding budgets.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Operations Justification', roles: 'operations_manager', rolesMode: 'any'}),
        createStep({label: 'Finance Partner Review', roles: 'finance_business_partner', rolesMode: 'any'}),
        createStep({label: 'CFO Approval', roles: 'cfo', rolesMode: 'any'}),
      ],
      fields: 'budget_ceiling',
      scenario: 'model',
    },
    controller: {
      description: 'Enforce approvals before raising departmental budget ceilings from the API.',
      fields: 'budget_ceiling,justification',
    },
  },
  sensitiveFeatureToggle: {
    label: 'Sensitive feature toggle',
    description: 'Product and security review before enabling sensitive feature flags.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Product Review', roles: 'product_lead', rolesMode: 'any'}),
        createStep({label: 'Security Sign-off', roles: 'security_architect', rolesMode: 'any'}),
      ],
      fields: 'feature_flags',
      scenario: 'model',
    },
    controller: {
      description: 'Intercept feature toggle requests for sensitive capabilities.',
      fields: 'feature_flags',
    },
  },
  vipRefund: {
    label: 'VIP refund escalation',
    description: 'High-value refunds require support leadership and finance sign-off.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Support Leadership Review', roles: 'support_lead', rolesMode: 'any'}),
        createStep({label: 'Finance Approval', roles: 'finance_director', rolesMode: 'any'}),
      ],
      fields: 'refund_amount,refund_reason',
      scenario: 'controller',
    },
    controller: {
      description: 'Block large VIP refunds until support and finance approve the payload.',
      fields: 'refund_amount,refund_reason',
    },
  },
  dataRetentionOverride: {
    label: 'Data retention override',
    description: 'Legal and privacy must approve exceptions to retention policies.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Legal Approval', roles: 'legal_counsel', rolesMode: 'any'}),
        createStep({label: 'Privacy Review', roles: 'privacy_officer', rolesMode: 'any'}),
      ],
      fields: 'retention_policy,retention_expires_at',
      scenario: 'model',
    },
    controller: {
      description: 'Intercept retention overrides initiated by services or dashboards.',
      fields: 'retention_policy,retention_expires_at',
    },
  },
  mlModelPromotion: {
    label: 'ML model promotion',
    description: 'ML lead, risk, and compliance approvals before promoting a model to production.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'ML Lead Review', roles: 'ml_lead', rolesMode: 'any'}),
        createStep({label: 'Risk Assessment', roles: 'risk_officer', rolesMode: 'any'}),
        createStep({label: 'Compliance Approval', roles: 'compliance_manager', rolesMode: 'any'}),
      ],
      fields: 'model_version,rollout_plan',
      scenario: 'model',
    },
    controller: {
      description: 'Require cross-functional approvals before promoting ML models via pipeline.',
      fields: 'model_version,rollout_plan',
    },
  },
  warehouseTransfer: {
    label: 'Warehouse transfer above threshold',
    description: 'Large inventory transfers need manager, finance, and logistics approvals.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Inventory Manager Review', roles: 'inventory_manager', rolesMode: 'any'}),
        createStep({label: 'Finance Controller Approval', roles: 'finance_controller', rolesMode: 'any'}),
        createStep({label: 'Logistics Coordination', roles: 'logistics_director', rolesMode: 'any'}),
      ],
      fields: 'transfer_quantity,transfer_value',
      scenario: 'controller',
    },
    controller: {
      description: 'Gate high-value warehouse transfers behind staged approvals.',
      fields: 'transfer_quantity,transfer_value',
    },
  },
  creditLimitRaise: {
    label: 'Customer credit limit raise',
    description: 'Account, risk, and finance leadership sign-off on major credit moves.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Account Owner Justification', roles: 'account_owner', rolesMode: 'any'}),
        createStep({label: 'Risk Review', roles: 'risk_analyst', rolesMode: 'any'}),
        createStep({label: 'Finance Leader Approval', roles: 'finance_director', rolesMode: 'any'}),
      ],
      fields: 'credit_limit',
      scenario: 'model',
    },
    controller: {
      description: 'Require staged approvals before increasing customer credit limits through the UI.',
      fields: 'credit_limit,credit_notes',
    },
  },
  marketingBlast: {
    label: 'Marketing blast to large audience',
    description: 'Marketing, legal, and deliverability approvals for big sends.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Marketing Approval', roles: 'marketing_manager', rolesMode: 'any'}),
        createStep({label: 'Legal Review', roles: 'legal_reviewer', rolesMode: 'any'}),
        createStep({label: 'Deliverability Check', roles: 'deliverability_lead', rolesMode: 'any'}),
      ],
      fields: 'audience_id,send_time,template_id',
      scenario: 'controller',
    },
    controller: {
      description: 'Ensure large audience sends are reviewed before scheduling.',
      fields: 'audience_id,send_time,template_id',
    },
  },
  rolePromotion: {
    label: 'Role promotion with elevated privileges',
    description: 'Manager and security must approve before granting higher roles.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Manager Approval', roles: 'manager', rolesMode: 'any'}),
        createStep({label: 'Security Review', roles: 'security_admin', rolesMode: 'any'}),
      ],
      fields: 'role_id,permissions',
      scenario: 'model',
    },
    controller: {
      description: 'Intercept role promotions that grant elevated permissions.',
      fields: 'role_id,permissions',
    },
  },
  customsDeclaration: {
    label: 'Customs declaration amendment',
    description: 'Logistics, compliance, and customs broker approvals before filing updates.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Logistics Review', roles: 'logistics_manager', rolesMode: 'any'}),
        createStep({label: 'Trade Compliance Approval', roles: 'trade_compliance', rolesMode: 'any'}),
        createStep({label: 'Broker Confirmation', roles: 'customs_broker', rolesMode: 'any'}),
      ],
      fields: 'declaration_status,tariff_codes',
      scenario: 'controller',
    },
    controller: {
      description: 'Require regulatory approvals before altering customs declarations.',
      fields: 'declaration_status,tariff_codes',
    },
  },
  soxJournalEntry: {
    label: 'SOX journal entry adjustment',
    description: 'Accounting manager, controller, and internal audit review required.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Accounting Manager Review', roles: 'accounting_manager', rolesMode: 'any'}),
        createStep({label: 'Controller Approval', roles: 'financial_controller', rolesMode: 'any'}),
        createStep({label: 'Internal Audit Sign-off', roles: 'internal_auditor', rolesMode: 'any'}),
      ],
      fields: 'journal_entry,adjustment_reason',
      scenario: 'model',
    },
    controller: {
      description: 'Enforce SOX-compliant approvals for sensitive journal entry adjustments.',
      fields: 'journal_entry,adjustment_reason',
    },
  },
  samlProviderChange: {
    label: 'SAML identity provider change',
    description: 'Security, IAM, and CTO approvals before swapping identity providers.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Security Engineering Review', roles: 'security_engineer', rolesMode: 'any'}),
        createStep({label: 'IAM Lead Approval', roles: 'iam_lead', rolesMode: 'any'}),
        createStep({label: 'CTO Sign-off', roles: 'cto', rolesMode: 'any'}),
      ],
      fields: 'saml_metadata,acs_url',
      scenario: 'controller',
    },
    controller: {
      description: 'Protect SAML configuration updates behind multi-party approvals.',
      fields: 'saml_metadata,acs_url',
    },
  },
  customerOffboarding: {
    label: 'Customer offboarding with data purge',
    description: 'Customer success, legal, and compliance approvals before purge.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Customer Success Review', roles: 'customer_success_manager', rolesMode: 'any'}),
        createStep({label: 'Legal Approval', roles: 'legal_counsel', rolesMode: 'any'}),
        createStep({label: 'Compliance Confirmation', roles: 'compliance_manager', rolesMode: 'any'}),
      ],
      fields: 'offboarding_status,purge_date',
      scenario: 'controller',
    },
    controller: {
      description: 'Ensure regulated data purges receive full approval trail.',
      fields: 'offboarding_status,purge_date',
    },
  },
  inventoryWriteOff: {
    label: 'Inventory write-off above threshold',
    description: 'Warehouse and finance oversight for significant write-offs.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Warehouse Verification', roles: 'warehouse_manager', rolesMode: 'any'}),
        createStep({label: 'Finance Approval', roles: 'finance_controller', rolesMode: 'any'}),
      ],
      fields: 'write_off_amount,write_off_reason',
      scenario: 'model',
    },
    controller: {
      description: 'Require approvals before committing large inventory write-offs.',
      fields: 'write_off_amount,write_off_reason',
    },
  },
  loanDisbursement: {
    label: 'Loan disbursement release',
    description: 'Loan officer, risk, and treasury approvals before disbursing funds.',
    defaults: {
      includeInitiator: false,
      steps: [
        createStep({label: 'Loan Officer Confirmation', roles: 'loan_officer', rolesMode: 'any'}),
        createStep({label: 'Risk Approval', roles: 'risk_officer', rolesMode: 'any'}),
        createStep({label: 'Treasury Release', roles: 'treasury_manager', rolesMode: 'any'}),
      ],
      fields: 'disbursement_amount,disbursement_date',
      scenario: 'controller',
    },
    controller: {
      description: 'Gate loan disbursements via multi-party approvals.',
      fields: 'disbursement_amount,disbursement_date',
    },
  },
  productionDeploy: {
    label: 'Production deploy with schema change',
    description: 'Engineering, QA, and SRE approvals before risky deploys.',
    defaults: {
      includeInitiator: true,
      steps: [
        createStep({
          label: 'Engineering Review',
          permissions: 'deployments.promote',
          permissionsMode: 'all',
          roles: 'lead_engineer',
          rolesMode: 'all',
        }),
        createStep({label: 'QA Sign-off', roles: 'qa_manager', rolesMode: 'any'}),
        createStep({label: 'SRE Approval', roles: 'sre_on_call', rolesMode: 'any'}),
      ],
      fields: 'deploy_plan,schema_migration',
      scenario: 'model',
    },
    controller: {
      description: 'Intercept deploy tasks that include schema changes for manual oversight.',
      fields: 'deploy_plan,schema_migration',
    },
  },
} satisfies Record<string, TemplateMeta>;

type TemplateKey = keyof typeof templatePresets;

function cloneFlowConfig(config: FlowConfig): FlowConfig {
  return {
    ...config,
    outputMode: config.outputMode ?? 'flow',
    configKey: config.configKey ?? 'feature.action',
    steps: config.steps.map((step) => ({...step})),
  };
}

function useDocsVersionName(): string {
  const [primary] = useDocsVersionCandidates('default');
  return primary?.name ?? 'current';
}

const CONTROL_TOOLTIPS = {
  templateSelect: 'Pick a preset to pre-fill steps, signer rules, and fields.',
  outputFlow: 'Generate Flow::make() code on your model.',
  outputConfig: 'Generate config/guardrails.php entry (dot keys + single-step shorthand).',
  outputController: 'Generate an approval hook for controller-based intercepts.',
  fields: 'Comma separated list of attributes or payload keys that Guardrails should protect.',
  configKey: 'Feature.action key stored under guardrails.flows (e.g., posts.publish).',
  includeInitiator: 'Counts the initiator toward thresholds when they meet signer rules.',
  stepLabel: 'Title shown to reviewers in the approval UI.',
  approvals: 'How many approvals must be collected before the step completes.',
  permissions: 'Comma separated permission identifiers required for a signer.',
  permissionsMode: 'Switch between any-of or all-of matching for required permissions.',
  samePermission: 'Require signers to share the initiator’s permission context.',
  roles: 'Comma separated role slugs that qualify an approver.',
  rolesMode: 'Switch between any-of or all-of matching for required roles.',
  sameRole: 'Require signers to share the initiator’s role.',
  signerRequirementsSummary: 'Expand to tune eligibility rules for who may approve the step.',
  minRejections: 'Minimum number of rejections before the step is marked rejected.',
  maxRejections: 'Maximum rejections to record before blocking further votes.',
  rejectionSummary: 'Expand to configure how rejection votes finalize the step.',
  moveUp: 'Move this step earlier in the approval order.',
  moveDown: 'Move this step later in the approval order.',
  removeStep: 'Remove this step from the flow.',
  addStep: 'Add another approval step at the end of the flow.'
} as const;

function parseCsv(value: string): string[] {
  return value
    .split(',')
    .map((item) => item.trim())
    .filter((item) => item.length > 0);
}

function escapePhpString(value: string): string {
  return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function formatPhpArray(list: string[]): string {
  return list.map((item) => `'${escapePhpString(item)}'`).join(', ');
}

function ensureList(list: string[], fallback: string): string[] {
  if (list.length > 0) {
    return list;
  }
  if (!fallback.trim()) {
    return [];
  }
  return parseCsv(fallback);
}

function sanitizeSteps(steps: FlowStep[], fallback: FlowStep[]): FlowStep[] {
  const base = steps.length > 0 ? steps : fallback;
  return base.map((step, index) => {
    const fallbackStep = fallback[index] ?? createStep({label: `Step ${index + 1}`});
    const merged = {
      ...createStep({label: `Step ${index + 1}`}),
      ...fallbackStep,
      ...step,
    };
    const label = merged.label?.trim().replace(/\s+/g, ' ');
    const minRejections = sanitizeThresholdValue(merged.minRejections);
    let maxRejections = sanitizeThresholdValue(merged.maxRejections);
    if (minRejections !== null && maxRejections !== null && maxRejections < minRejections) {
      maxRejections = minRejections;
    }

    return {
      ...merged,
      label: label && label.length ? label : `Step ${index + 1}`,
      approvals: merged.approvals && merged.approvals > 0 ? Math.round(merged.approvals) : 1,
      permissions: merged.permissions ?? '',
      permissionsMode: merged.permissionsMode === 'all' ? 'all' : 'any',
      roles: merged.roles ?? '',
      rolesMode: merged.rolesMode === 'all' ? 'all' : 'any',
      samePermissionAsInitiator: Boolean(merged.samePermissionAsInitiator),
      sameRoleAsInitiator: Boolean(merged.sameRoleAsInitiator),
      minRejections,
      maxRejections,
    };
  });
}

function sanitizeThresholdValue(value: unknown): number | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric < 1) {
    return null;
  }

  return Math.round(numeric);
}

function formatStepComment(label: string, index: number): string {
  const sanitized = label.replace(/\r?\n/g, ' ').replace(/\*\//g, '*\/');
  return `Step ${index + 1} - ${sanitized}`;
}

function buildFlowLines(values: FlowConfig, templateKey: TemplateKey, preset: TemplateMeta): string[] {
  const steps = sanitizeSteps(values.steps, preset.defaults.steps);
  const lines: string[] = ['Flow::make() // Start a new Guardrails flow'];

  if (values.includeInitiator) {
    lines.push('    ->includeInitiator(true, true)');
  }

  steps.forEach((step, index) => {
    const permissions = parseCsv(step.permissions);
    const roles = parseCsv(step.roles);

    lines.push(`    // ${formatStepComment(step.label, index)}`);

    if (permissions.length) {
      if (step.permissionsMode === 'all') {
        lines.push(`    ->permissions([${formatPhpArray(permissions)}])`);
      } else {
        lines.push(`    ->anyOfPermissions([${formatPhpArray(permissions)}])`);
      }
    }

    if (roles.length) {
      if (step.rolesMode === 'all') {
        lines.push(`    ->roles([${formatPhpArray(roles)}])`);
      } else {
        lines.push(`    ->anyOfRoles([${formatPhpArray(roles)}])`);
      }
    }

    if (step.samePermissionAsInitiator) {
      lines.push('    ->samePermissionAsInitiator(true)');
    }

    if (step.sameRoleAsInitiator) {
      lines.push('    ->sameRoleAsInitiator(true)');
    }

    if (step.minRejections !== null && step.maxRejections !== null) {
      lines.push(`    ->rejectionThreshold(${step.minRejections}, ${step.maxRejections})`);
    } else if (step.minRejections !== null) {
      lines.push(`    ->minRejections(${step.minRejections})`);
    } else if (step.maxRejections !== null) {
      lines.push(`    ->maxRejections(${step.maxRejections})`);
    }

    const label = escapePhpString(step.label);
    lines.push(`    ->signedBy(${step.approvals}, '${label}')`);
  });

  lines.push('    // Finalize flow definition');
  lines.push('    ->build(),');

  return lines;
}

function humanizeField(field: string): string {
  return field
    .replace(/_/g, ' ')
    .replace(/\b(id)\b$/i, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function guessBodyParam(field: string): {type: string; example: string; description: string} {
  const lower = field.toLowerCase();
  const label = humanizeField(field);
  const capitalLabel = label.charAt(0).toUpperCase() + label.slice(1);
  const numericKeywords = ['amount', 'total', 'value', 'quantity', 'count', 'number', 'price', 'rate', 'limit'];
  const booleanKeywords = ['enabled', 'active', 'flag'];
  if (lower.endsWith('id') || lower.includes('_id')) {
    return {
      type: 'integer',
      example: '1',
      description: `${capitalLabel || 'Resource'} identifier that the change targets.`,
    };
  }
  if (numericKeywords.some((keyword) => lower.includes(keyword))) {
    return {
      type: 'number',
      example: '199.50',
      description: `${capitalLabel || 'Numeric value'} used when applying the change.`,
    };
  }
  if (booleanKeywords.some((keyword) => lower.includes(keyword))) {
    return {
      type: 'boolean',
      example: 'true',
      description: `Whether ${label || 'this flag'} should be enabled after approval.`,
    };
  }
  if (lower.includes('status')) {
    return {
      type: 'string',
      example: '"approved"',
      description: `New status value applied to ${label || 'the resource'}.`,
    };
  }
  if (lower.includes('name')) {
    return {
      type: 'string',
      example: '"Jane Doe"',
      description: `Name associated with ${label || 'the record'}.`,
    };
  }
  return {
    type: 'string',
    example: `"${label || 'value'}"`,
    description: `${capitalLabel || 'Value'} forwarded for approval.`,
  };
}

function buildModelSnippet(values: FlowConfig, templateKey: TemplateKey): string {
  const preset = templatePresets[templateKey];
  const guardList = ensureList(parseCsv(values.fields), preset.defaults.fields);
  const flowLines = buildFlowLines(values, templateKey, preset);

  return `<?php\n\nuse Illuminate\\Database\\Eloquent\\Model;\nuse OVAC\\Guardrails\\Concerns\\Guardrail;\nuse OVAC\\Guardrails\\Services\\Flow;\n\n/**\n * Example model used in the Guardrails playground.\n */\nclass ExampleModel extends Model\n{\n    use Guardrail;\n\n    /**\n     * Guardrails should watch these attributes for approval.\n     *\n     * @return array<int, string> Attribute keys that require review before persisting.\n     */\n    public function guardrailAttributes(): array\n    {\n        return [${formatPhpArray(guardList)}];\n    }\n\n    /**\n     * Describe the Guardrails approval workflow for this model.\n     *\n     * @param  array<string, mixed>  $dirty  Pending attribute changes captured for approval.\n     * @param  string  $event  Model lifecycle event (creating/updating/custom) that triggered capture.\n     * @return array<int, array<string, mixed>> Normalized multi-step Guardrails definition.\n     */\n    public function guardrailApprovalFlow(array $dirty, string $event): array\n    {\n        return [\n${flowLines.map((line) => `            ${line}`).join('\n')}\n        ];\n    }\n}\n`;
}

function buildControllerSnippet(values: FlowConfig, templateKey: TemplateKey): string {
  const preset = templatePresets[templateKey];
  const allowedFields = ensureList(parseCsv(values.fields), preset.controller.fields);
  const flowLines = buildFlowLines(values, templateKey, preset);
  const controllerClass = values.scenario === 'model' ? 'ExampleModelController' : 'ExampleController';
  const controllerSummary =
    values.scenario === 'model'
      ? 'Update ExampleModel via Guardrails approvals.'
      : 'Submit controller changes through Guardrails approvals.';

  const fieldsForDocs = allowedFields.length ? allowedFields : ['payload'];
  const bodyParamLines = fieldsForDocs.map((field) => {
    const {type, example, description} = guessBodyParam(field);
    return `     * @bodyParam ${field} ${type} required ${description} Example: ${example}`;
  });
  const bodyParamSection = `${bodyParamLines.join('\n')}\n     *\n`;

  return `<?php\n\nuse App\\Http\\Controllers\\Controller;\nuse Illuminate\\Http\\Request;\nuse OVAC\\Guardrails\\Http\\Concerns\\InteractsWithGuardrail;\nuse OVAC\\Guardrails\\Services\\Flow;\nuse OVAC\\Guardrails\\Support\\SigningPolicy;\n\nclass ${controllerClass} extends Controller\n{\n    /** Enables Guardrails controller interception helpers. */\n    use InteractsWithGuardrail;\n\n    /**\n     * ${controllerSummary}\n     *\n${bodyParamSection}     * @param  Request       $request  Incoming HTTP request containing validated data.\n     * @param  ExampleModel  $model    Domain model instance that is being changed.\n     * @return array{captured: bool, request_id: ?string, changes: array<string, mixed>} Guardrails capture metadata.\n     */\n    public function update(Request $request, ExampleModel $model): array\n    {\n        /** @var array<string, mixed> $data */\n        $data = $request->validated([${formatPhpArray(allowedFields)}]);\n\n        return $this->guardrailIntercept($model, $data, [\n            'description' => '${escapePhpString(preset.controller.description)}',\n            'only' => [${formatPhpArray(allowedFields)}],\n            'extender' => function (SigningPolicy $policy): array {\n                return [\n${flowLines.map((line) => `                    ${line}`).join('\n')}\n                ];\n            },\n        ]);\n    }\n}\n`;
}

function buildConfigStep(step: ReturnType<typeof sanitizeSteps>[number], includeInitiator: boolean, wrapInArray: boolean): string {
  const permissions = parseCsv(step.permissions);
  const roles = parseCsv(step.roles);
  const signerLines = [
    "'guard' => 'web',",
    `            'permissions' => [${formatPhpArray(permissions)}],`,
    `            'permissions_mode' => '${step.permissionsMode}',`,
    `            'roles' => [${formatPhpArray(roles)}],`,
    `            'roles_mode' => '${step.rolesMode}',`,
    `            'same_permission_as_initiator' => ${step.samePermissionAsInitiator ? 'true' : 'false'},`,
    `            'same_role_as_initiator' => ${step.sameRoleAsInitiator ? 'true' : 'false'},`,
  ];

  const metaLines = [
    `            'include_initiator' => ${includeInitiator ? 'true' : 'false'},`,
    "            'preapprove_initiator' => true,",
  ];

  if (step.minRejections !== null) {
    metaLines.push(`            'rejection_min' => ${step.minRejections},`);
  }
  if (step.maxRejections !== null) {
    metaLines.push(`            'rejection_max' => ${step.maxRejections},`);
  }

  const outerIndent = '        ';
  const innerIndent = wrapInArray ? '            ' : outerIndent;

  const lines = wrapInArray ? [`${outerIndent}[`] : [];

  lines.push(
    `${innerIndent}'name' => '${escapePhpString(step.label)}',`,
    `${innerIndent}'threshold' => ${step.approvals},`,
    `${innerIndent}'signers' => [`,
    ...signerLines,
    `${innerIndent}],`,
    `${innerIndent}'meta' => [`,
    ...metaLines,
    `${innerIndent}],`
  );

  if (wrapInArray) {
    lines.push(`${outerIndent}]`);
  }

  return lines.join('\n');
}

function buildConfigSnippet(values: FlowConfig, templateKey: TemplateKey): string {
  const preset = templatePresets[templateKey];
  const steps = sanitizeSteps(values.steps, preset.defaults.steps);
  const includeInitiator = Boolean(values.includeInitiator);
  const key = (values.configKey || 'feature.action').trim() || 'feature.action';
  const wrapInArray = steps.length > 1;

  const stepBlocks = steps
    .map((step) => buildConfigStep(step, includeInitiator, wrapInArray))
    .map((block, index) => (wrapInArray ? `${block}${index < steps.length - 1 ? ',' : ''}` : block));

  const indentedBlocks = stepBlocks
    .map((block) => block.split('\n').map((line) => `            ${line}`).join('\n'))
    .join('\n');

  return `<?php\n\nreturn [\n    'flows' => [\n        '${escapePhpString(key)}' => [\n${indentedBlocks}\n        ],\n    ],\n];\n`;
}

export default function Playground(): JSX.Element {
  const [template, setTemplate] = useState<TemplateKey>('twoPerson');
  const [values, setValues] = useState<FlowConfig>(() => cloneFlowConfig(templatePresets.twoPerson.defaults));
  const [copied, setCopied] = useState(false);
  const [dragIndex, setDragIndex] = useState<number | null>(null);
  const docsVersionName = useDocsVersionName();
  const supportsConfigOutput = docsVersionName !== '1.0.0';
  const {colorMode} = useColorMode();
  const syntaxTheme = colorMode === 'dark' ? dracula : oneLight;
  const previewScrollRef = useRef<HTMLDivElement | null>(null);
  const initialScrollDoneRef = useRef(false);
  const controllerScrollDoneRef = useRef(false);

  const effectiveOutputMode: OutputMode =
    !supportsConfigOutput && values.outputMode === 'config' ? 'flow' : values.outputMode ?? 'flow';

  const snippet = useMemo(() => {
    if (effectiveOutputMode === 'controller') {
      return buildControllerSnippet(values, template);
    }
    if (effectiveOutputMode === 'config' && supportsConfigOutput) {
      return buildConfigSnippet(values, template);
    }
    return buildModelSnippet(values, template);
  }, [effectiveOutputMode, supportsConfigOutput, template, values]);

  useEffect(() => {
    if (!supportsConfigOutput && values.outputMode === 'config') {
      setValues((prev) => ({...prev, outputMode: 'flow', scenario: 'model'}));
    }
  }, [supportsConfigOutput, values.outputMode]);

  useEffect(() => {
    const container = previewScrollRef.current;
    if (!container) {
      return;
    }

    const scroller = container.querySelector('pre');
    if (!(scroller instanceof HTMLElement)) {
      return;
    }

    if (!initialScrollDoneRef.current) {
      scroller.scrollTop = scroller.scrollHeight;
      initialScrollDoneRef.current = true;
      controllerScrollDoneRef.current = values.scenario === 'controller';
      return;
    }

    if (values.scenario === 'controller' && !controllerScrollDoneRef.current) {
      scroller.scrollTop = scroller.scrollHeight;
      controllerScrollDoneRef.current = true;
    }
  }, [snippet, values.scenario]);

  function handleChange<K extends keyof FlowConfig>(key: K, newValue: FlowConfig[K]) {
    setValues((prev) => ({...prev, [key]: newValue}));
  }

  function handleTemplateChange(event: React.ChangeEvent<HTMLSelectElement>) {
    const nextTemplate = event.target.value as TemplateKey;
    setTemplate(nextTemplate);
    const presetDefaults = cloneFlowConfig(templatePresets[nextTemplate].defaults);
    setValues((prev) => {
      const desiredOutput = prev.outputMode ?? presetDefaults.outputMode;
      const nextOutput = !supportsConfigOutput && desiredOutput === 'config' ? 'flow' : desiredOutput;
      const nextScenario = nextOutput === 'controller' ? 'controller' : 'model';
      return {
        ...presetDefaults,
        outputMode: nextOutput,
        scenario: nextScenario,
        configKey: prev.configKey || presetDefaults.configKey,
      };
    });
  }

  function handleOutputModeChange(mode: OutputMode) {
    const nextMode = !supportsConfigOutput && mode === 'config' ? 'flow' : mode;
    setValues((prev) => ({
      ...prev,
      outputMode: nextMode,
      scenario: nextMode === 'controller' ? 'controller' : 'model',
    }));
  }

  function handleStepChange(index: number, updater: (step: FlowStep) => FlowStep) {
    setValues((prev) => ({
      ...prev,
      steps: prev.steps.map((step, idx) => (idx === index ? updater(step) : step)),
    }));
  }

  function addStep() {
    setValues((prev) => {
      const presetSteps = templatePresets[template].defaults.steps;
      const nextIndex = prev.steps.length;
      const templateStep = presetSteps[nextIndex];
      const base = templateStep ? {...templateStep} : {};
      const label = base.label ?? `Step ${nextIndex + 1}`;
      const nextStep: FlowStep = {
        ...createStep({label}),
        ...base,
      };
      return {
        ...prev,
        steps: [...prev.steps, nextStep],
      };
    });
  }

  function removeStep(index: number) {
    setValues((prev) => ({
      ...prev,
      steps: prev.steps.length > 1 ? prev.steps.filter((_, idx) => idx !== index) : prev.steps,
    }));
  }

  function moveStep(index: number, direction: -1 | 1) {
    setValues((prev) => {
      const nextIndex = index + direction;
      if (nextIndex < 0 || nextIndex >= prev.steps.length) {
        return prev;
      }
      const steps = [...prev.steps];
      const [step] = steps.splice(index, 1);
      steps.splice(nextIndex, 0, step);
      return {...prev, steps};
    });
  }

  function handleDragStart(index: number) {
    setDragIndex(index);
  }

  function handleDragOver(event: React.DragEvent<HTMLDivElement>) {
    event.preventDefault();
  }

  function handleDrop(index: number) {
    if (dragIndex === null || dragIndex === index) {
      setDragIndex(null);
      return;
    }
    setValues((prev) => {
      const steps = [...prev.steps];
      const [step] = steps.splice(dragIndex, 1);
      steps.splice(index, 0, step);
      return {...prev, steps};
    });
    setDragIndex(null);
  }

  function handleDragEnd() {
    setDragIndex(null);
  }

  function updateStepLabel(index: number, label: string) {
    handleStepChange(index, (step) => ({...step, label}));
  }

  function updateStepApprovals(index: number, approvals: number) {
    const safeApprovals = Number.isFinite(approvals) && approvals > 0 ? Math.round(approvals) : 1;
    handleStepChange(index, (step) => ({...step, approvals: safeApprovals}));
  }

  function updateStepMinRejections(index: number, raw: string) {
    const parsed = parseThresholdInput(raw);
    handleStepChange(index, (step) => {
      const next = {...step, minRejections: parsed};
      if (parsed !== null && step.maxRejections !== null && step.maxRejections < parsed) {
        next.maxRejections = parsed;
      }
      return next;
    });
  }

  function updateStepMaxRejections(index: number, raw: string) {
    const parsed = parseThresholdInput(raw);
    handleStepChange(index, (step) => {
      const next = {...step, maxRejections: parsed};
      if (parsed !== null && step.minRejections !== null && parsed < step.minRejections) {
        next.minRejections = parsed;
      }
      return next;
    });
  }

  function updateStepPermissions(index: number, permissions: string) {
    handleStepChange(index, (step) => ({...step, permissions}));
  }

  function updateStepPermissionsMode(index: number, mode: StepMode) {
    handleStepChange(index, (step) => ({...step, permissionsMode: mode}));
  }

  function updateStepRoles(index: number, roles: string) {
    handleStepChange(index, (step) => ({...step, roles}));
  }

  function updateStepRolesMode(index: number, mode: StepMode) {
    handleStepChange(index, (step) => ({...step, rolesMode: mode}));
  }

  function updateStepSamePermission(index: number, value: boolean) {
    handleStepChange(index, (step) => ({...step, samePermissionAsInitiator: value}));
  }

  function updateStepSameRole(index: number, value: boolean) {
    handleStepChange(index, (step) => ({...step, sameRoleAsInitiator: value}));
  }

  function parseThresholdInput(raw: string): number | null {
    const trimmed = raw.trim();
    if (!trimmed.length) {
      return null;
    }
    const parsed = Number(trimmed);
    if (!Number.isFinite(parsed) || parsed < 1) {
      return null;
    }
    return Math.round(parsed);
  }

  async function copyToClipboard() {
    await navigator.clipboard.writeText(snippet);
    setCopied(true);
    setTimeout(() => setCopied(false), 1600);
  }

  const preset = templatePresets[template];

  return (
    <div className="playground">
      <div className="playground__card">
        <div className="playground__layout">
          <div className="playground__controls">
            <header className="playground__header">
              <div className="playground__scenario">
                <label htmlFor="template">Scenario</label>
                <select id="template" value={template} onChange={handleTemplateChange} title={CONTROL_TOOLTIPS.templateSelect}>
                  {Object.entries(templatePresets).map(([key, meta]) => (
                    <option key={key} value={key}>
                      {meta.label}
                    </option>
                  ))}
                </select>
                <p>{preset.description}</p>
              </div>
              <div className="playground__toggle" role="radiogroup" aria-label="Generate snippet for">
                <label className={values.outputMode === 'flow' ? 'active' : ''} title={CONTROL_TOOLTIPS.outputFlow}>
                  <input
                    type="radio"
                    name="outputMode"
                    value="flow"
                    checked={values.outputMode === 'flow'}
                    onChange={() => handleOutputModeChange('flow')}
                  />
                  Flow builder
                </label>
                {supportsConfigOutput && (
                  <label className={values.outputMode === 'config' ? 'active' : ''} title={CONTROL_TOOLTIPS.outputConfig}>
                    <input
                      type="radio"
                      name="outputMode"
                      value="config"
                      checked={values.outputMode === 'config'}
                      onChange={() => handleOutputModeChange('config')}
                    />
                    Config entry
                  </label>
                )}
                <label className={values.outputMode === 'controller' ? 'active' : ''} title={CONTROL_TOOLTIPS.outputController}>
                  <input
                    type="radio"
                    name="outputMode"
                    value="controller"
                    checked={values.outputMode === 'controller'}
                    onChange={() => handleOutputModeChange('controller')}
                  />
                  Controller intercept
                </label>
              </div>
              {!supportsConfigOutput && (
                <p className="playground__note">Config output is available in v1.0.1 and later.</p>
              )}
            </header>

        <div className="playground__grid">
          {values.outputMode === 'config' && supportsConfigOutput && (
            <label className="playground__field playground__field--wide">
              <span>Config key (feature.action)</span>
              <input
                id="configKey"
                type="text"
                value={values.configKey}
                onChange={(event) => handleChange('configKey', event.target.value)}
                title={CONTROL_TOOLTIPS.configKey}
              />
            </label>
          )}
          <label className="playground__field playground__field--wide">
            <span>
              {values.outputMode === 'controller'
                ? 'Allowed fields (comma separated)'
                : 'Guarded attributes (comma separated)'}
            </span>
            <input
              id="fields"
              type="text"
              value={values.fields}
              onChange={(event) => handleChange('fields', event.target.value)}
              title={CONTROL_TOOLTIPS.fields}
            />
          </label>
          <label className="playground__checkbox" title={CONTROL_TOOLTIPS.includeInitiator}>
            <input
              type="checkbox"
              checked={values.includeInitiator}
              onChange={(event) => handleChange('includeInitiator', event.target.checked)}
            />
            Count initiator as signer
          </label>
        </div>

            <div className="playground__steps">
              <div className="playground__stepsHeader">
                <h3>Approval steps</h3>
                <p>Configure approval counts and optional rejection thresholds.</p>
              </div>
          {values.steps.map((step, index) => {
            const defaultOpen = Boolean(step.permissions.trim() || step.roles.trim() || step.samePermissionAsInitiator || step.sameRoleAsInitiator);
            const rejectionDefaultsSet = step.minRejections !== null || step.maxRejections !== null;
            const rejectionSummaryHint = rejectionDefaultsSet
              ? `Min ${step.minRejections ?? '—'} · Max ${step.maxRejections ?? '—'}`
              : 'Optional';
            return (
              <div
                key={`step-${index}`}
                className={`playground__step${dragIndex === index ? ' playground__step--dragging' : ''}`}
                draggable
                onDragStart={() => handleDragStart(index)}
                onDragOver={handleDragOver}
                onDrop={() => handleDrop(index)}
                onDragEnd={handleDragEnd}
              >
                <div className="playground__stepHeader">
                  <strong>Step {index + 1}</strong>
                  <div className="playground__stepActions">
                    {index > 0 && (
                      <button
                        className="playground__move"
                        type="button"
                        onClick={() => moveStep(index, -1)}
                        aria-label="Move step up"
                        title={CONTROL_TOOLTIPS.moveUp}
                      >
                        ↑
                      </button>
                    )}
                    {index < values.steps.length - 1 && (
                      <button
                        className="playground__move"
                        type="button"
                        onClick={() => moveStep(index, 1)}
                        aria-label="Move step down"
                        title={CONTROL_TOOLTIPS.moveDown}
                      >
                        ↓
                      </button>
                    )}
                    {values.steps.length > 1 && (
                      <button
                        className="playground__removeStep"
                        type="button"
                        onClick={() => removeStep(index)}
                        aria-label={`Remove step ${index + 1}`}
                        title={CONTROL_TOOLTIPS.removeStep}
                      >
                        ×
                      </button>
                    )}
                  </div>
                </div>
                <div className="playground__stepGrid">
                  <label className="playground__field playground__stepLabel">
                    <span>Label</span>
                    <input
                      type="text"
                      value={step.label}
                      onChange={(event) => updateStepLabel(index, event.target.value)}
                      title={CONTROL_TOOLTIPS.stepLabel}
                    />
                  </label>
                  <label className="playground__field playground__stepApprovals">
                    <span>Approvals</span>
                    <input
                      type="number"
                      min={1}
                      max={10}
                      step={1}
                      value={step.approvals}
                      onChange={(event) => updateStepApprovals(index, Number(event.target.value))}
                      title={CONTROL_TOOLTIPS.approvals}
                    />
                  </label>
                </div>
                <details className="playground__stepAdvanced" defaultOpen={defaultOpen}>
                  <summary title={CONTROL_TOOLTIPS.signerRequirementsSummary}>Signer requirements</summary>
                  <div className="playground__stepAdvancedGrid">
                    <label className="playground__field playground__stepWide">
                      <span>Permissions (comma separated)</span>
                      <input
                        type="text"
                        value={step.permissions}
                        onChange={(event) => updateStepPermissions(index, event.target.value)}
                        title={CONTROL_TOOLTIPS.permissions}
                      />
                    </label>
                    <label className="playground__field playground__stepMode">
                      <span>Permission mode</span>
                      <select
                        value={step.permissionsMode}
                        onChange={(event) => updateStepPermissionsMode(index, event.target.value as StepMode)}
                        title={CONTROL_TOOLTIPS.permissionsMode}
                      >
                        <option value="any">Any listed (any-of)</option>
                        <option value="all">All listed (all-of)</option>
                      </select>
                    </label>
                    <label className="playground__checkbox playground__stepToggle" title={CONTROL_TOOLTIPS.samePermission}>
                      <input
                        type="checkbox"
                        checked={step.samePermissionAsInitiator}
                        onChange={(event) => updateStepSamePermission(index, event.target.checked)}
                      />
                      Same permission as initiator
                    </label>
                    <label className="playground__field playground__stepWide">
                      <span>Roles (comma separated)</span>
                      <input
                        type="text"
                        value={step.roles}
                        onChange={(event) => updateStepRoles(index, event.target.value)}
                        title={CONTROL_TOOLTIPS.roles}
                      />
                    </label>
                    <label className="playground__field playground__stepMode">
                      <span>Role mode</span>
                      <select
                        value={step.rolesMode}
                        onChange={(event) => updateStepRolesMode(index, event.target.value as StepMode)}
                        title={CONTROL_TOOLTIPS.rolesMode}
                      >
                        <option value="any">Any listed (any-of)</option>
                        <option value="all">All listed (all-of)</option>
                      </select>
                    </label>
                    <label className="playground__checkbox playground__stepToggle" title={CONTROL_TOOLTIPS.sameRole}>
                      <input
                        type="checkbox"
                        checked={step.sameRoleAsInitiator}
                        onChange={(event) => updateStepSameRole(index, event.target.checked)}
                      />
                      Same role as initiator
                    </label>
                  </div>
                </details>
                <details className="playground__stepRejections" defaultOpen={rejectionDefaultsSet}>
                  <summary title={CONTROL_TOOLTIPS.rejectionSummary}>
                    Rejection thresholds
                    <span className="playground__stepSummaryHint">{rejectionSummaryHint}</span>
                  </summary>
                  <div className="playground__stepRejectionsGrid">
                    <label className="playground__field playground__stepThreshold">
                      <span>Min rejections</span>
                      <input
                        type="text"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        placeholder="none"
                        value={step.minRejections !== null ? String(step.minRejections) : ''}
                        onChange={(event) => updateStepMinRejections(index, event.target.value)}
                        title={CONTROL_TOOLTIPS.minRejections}
                      />
                    </label>
                    <label className="playground__field playground__stepThreshold">
                      <span>Max rejections</span>
                      <input
                        type="text"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        placeholder="none"
                        value={step.maxRejections !== null ? String(step.maxRejections) : ''}
                        onChange={(event) => updateStepMaxRejections(index, event.target.value)}
                        title={CONTROL_TOOLTIPS.maxRejections}
                      />
                    </label>
                  </div>
                </details>
              </div>
            );
          })}
              <button className="playground__addStep" type="button" onClick={addStep} title={CONTROL_TOOLTIPS.addStep}>
                Add step
              </button>
            </div>

            {values.outputMode === 'controller' && (
              <p className="playground__note">{preset.controller.description}</p>
            )}
          </div>

          <div className="playground__previewPanel">
            <div className="playground__previewHeader">
              <button className="playground__copy" type="button" onClick={copyToClipboard}>
                {copied ? 'Copied!' : 'Copy snippet'}
              </button>
              <span className="playground__help">Preview updates instantly as you tweak settings.</span>
            </div>
          <div className="playground__preview" ref={previewScrollRef}>
              <SyntaxHighlighter
                language="php"
                style={syntaxTheme}
                customStyle={{
                  margin: 0,
                  background: 'var(--playground-code-bg)',
                  border: '1px solid var(--playground-code-border)',
                  borderRadius: '14px',
                  padding: '0.75rem 1rem',
                  maxHeight: '78vh',
                  overflow: 'auto',
                  boxShadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.6)',
                }}
                codeTagProps={{
                  style: {
                    fontSize: '0.74rem',
                    lineHeight: '1.45',
                    fontFamily: 'var(--ifm-font-family-monospace)',
                  },
                }}
              >
                {snippet}
              </SyntaxHighlighter>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
