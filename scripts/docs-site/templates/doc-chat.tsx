import React, {useEffect, useMemo, useState} from 'react';
import type {ChangeEvent, FormEvent} from 'react';
import {Key, Send, RefreshCw, FileText, Cpu, Eye, EyeOff, Link} from 'lucide-react';
import docsIndex from '@site/static/docs-index.json';

const storageKey = 'guardrails-docs-openai-key';

type Section = { id: string; title: string; content: string; href: string };

type ChatMessage = { role: 'user' | 'assistant'; content: string };

type CompletionMessage = { role: 'system' | 'user' | 'assistant'; content: string };

function tokenize(value: string): string[] {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .split(/\s+/)
    .filter(Boolean);
}

const preparedIndex: Array<Section & {tokens: string[]}> = (docsIndex as Section[]).map((section) => ({
  ...section,
  tokens: tokenize(section.content + ' ' + section.title),
}));

function rankSections(query: string, limit = 3): Section[] {
  const queryTokens = tokenize(query);
  if (!queryTokens.length) {
    return preparedIndex.slice(0, limit);
  }

  const scored = preparedIndex
    .map((section) => {
      const score = queryTokens.reduce((acc, token) => acc + (section.tokens.includes(token) ? 1 : 0), 0);
      const boost = queryTokens.some((token) => section.title.toLowerCase().includes(token)) ? 2 : 0;
      return {section, score: score + boost};
    })
    .filter((item) => item.score > 0)
    .sort((a, b) => b.score - a.score)
    .slice(0, limit)
    .map((item) => item.section);

  if (!scored.length) {
    return preparedIndex.slice(0, limit);
  }

  return scored;
}

const defaultModel = 'gpt-4o-mini';

export default function DocChat(): JSX.Element {
  const [apiKey, setApiKey] = useState('');
  const [showKey, setShowKey] = useState(false);
  const [model, setModel] = useState(defaultModel);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [contextSections, setContextSections] = useState<Section[]>([]);
  const [copiedLinks, setCopiedLinks] = useState(false);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const savedKey = window.localStorage.getItem(storageKey);
    if (savedKey) setApiKey(savedKey);
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (apiKey) window.localStorage.setItem(storageKey, apiKey);
    else window.localStorage.removeItem(storageKey);
  }, [apiKey]);

  const contextHelp = useMemo(() => {
    if (!messages.length) return 'Ask anything about installing, configuring, or extending Guardrails.';
    return undefined;
  }, [messages.length]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    const trimmed = input.trim();
    if (!trimmed) return;
    if (!apiKey) {
      setError('Add an OpenAI-compatible API key to start chatting. The key is only stored in your browser.');
      return;
    }

    const userMessage: ChatMessage = {role: 'user', content: trimmed};
    setMessages((prev) => [...prev, userMessage]);
    setInput('');
    setLoading(true);

    const relevantSections = rankSections(trimmed + ' ' + messages.map((m) => m.content).join(' '));
    setContextSections(relevantSections);
    const context = relevantSections.map((s) => `### ${s.title}\n${s.content}`).join('\n\n');

    const completionMessages: CompletionMessage[] = [
      {
        role: 'system',
        content:
          'You are GuardrailsAI, a documentation assistant for the ovac/guardrails Laravel package. Answer with concise explanations, cite relevant section titles when possible, and prefer returning runnable PHP examples. Use the provided documentation context verbatim when it answers the question.',
      },
      {role: 'system', content: `Guardrails documentation context:\n\n${context}`},
      ...messages.map((message) => ({role: message.role, content: message.content} as CompletionMessage)),
      {role: 'user', content: trimmed},
    ];

    try {
      const response = await fetch('https://api.openai.com/v1/chat/completions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${apiKey}` },
        body: JSON.stringify({ model, messages: completionMessages, temperature: 0.2, max_tokens: 800 }),
      });

      if (!response.ok) {
        const details = await response.json().catch(() => ({}));
        throw new Error(details.error?.message || `API request failed with status ${response.status}`);
      }

      const payload = await response.json();
      const content: string | undefined = payload?.choices?.[0]?.message?.content;
      if (!content) throw new Error('No content returned by the model.');
      setMessages((prev) => [...prev, {role: 'assistant', content}]);
    } catch (requestError) {
      const message = requestError instanceof Error ? requestError.message : 'Unknown error retrieving completion.';
      setError(message);
    } finally {
      setLoading(false);
    }
  }

  function clearConversation() {
    setMessages([]);
    setError(null);
    setContextSections([]);
  }

  function copyContextLinks() {
    if (!contextSections.length) {
      return;
    }
    navigator.clipboard?.writeText(contextSections.map((section) => section.href).join('\n'));
    setCopiedLinks(true);
    setTimeout(() => setCopiedLinks(false), 1800);
  }

  return (
    <div className="assistant">
      <div className="assistant__card">
        <header className="assistant__header">
          <div className="assistant__title">
            <FileText size={28} aria-hidden="true" />
            <div>
              <h1>Guardrails AI Assistant</h1>
              <p>
                Connect your OpenAI, OpenRouter, or compatible key and get grounded answers referencing the Guardrails documentation.
                Keys never leave your browser.
              </p>
            </div>
          </div>
          <label className="assistant__model">
            <Cpu size={16} aria-hidden="true" />
            <span>Model</span>
            <select value={model} onChange={(event: ChangeEvent<HTMLSelectElement>) => setModel(event.target.value)}>
              <option value="gpt-4o-mini">gpt-4o-mini</option>
              <option value="gpt-4o">gpt-4o</option>
              <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
            </select>
          </label>
        </header>

        <div className="assistant__body">
          <aside className="assistant__sidebar">
            <div className="assistant__field">
              <label htmlFor="apiKey">OpenAI-compatible API key</label>
              <div className="assistant__keyRow">
                <Key size={16} aria-hidden="true" />
                <input
                  id="apiKey"
                  type={showKey ? 'text' : 'password'}
                  placeholder="sk-..."
                  value={apiKey}
                  onChange={(event) => setApiKey(event.target.value)}
                />
                <button type="button" onClick={() => setShowKey((prev) => !prev)} aria-label={showKey ? 'Hide API key' : 'Show API key'}>
                  {showKey ? <EyeOff size={16} aria-hidden="true" /> : <Eye size={16} aria-hidden="true" />}
                </button>
              </div>
              <p className="assistant__hint">Stored locally in your browser. Works with OpenAI, OpenRouter, or any compatible endpoints.</p>
            </div>

            <div className="assistant__summary">
              <strong>Context hint</strong>
              <p>{contextHelp ?? 'Conversation in progress — relevant doc sections are pinned below.'}</p>
            </div>

            {contextSections.length > 0 && (
              <div className="assistant__context">
                <div className="assistant__contextHeading">
                  <Link size={14} aria-hidden="true" />
                  <span>Context from docs</span>
                </div>
                <ul>
                  {contextSections.map((section) => (
                    <li key={section.id}>
                      <a href={section.href} target="_blank" rel="noreferrer">
                        {section.title}
                      </a>
                    </li>
                  ))}
                </ul>
                <button type="button" className="assistant__copy" onClick={copyContextLinks}>
                  <RefreshCw size={14} aria-hidden="true" /> {copiedLinks ? 'Copied' : 'Copy links'}
                </button>
              </div>
            )}

            {error && <div className="assistant__error">{error}</div>}

            <button type="button" className="assistant__reset" onClick={clearConversation} disabled={loading || messages.length === 0}>
              <RefreshCw size={14} aria-hidden="true" /> Reset conversation
            </button>
          </aside>

          <section className="assistant__conversation">
            <div className="assistant__messages" aria-live="polite">
              {messages.length === 0 ? (
                <div className="assistant__placeholder">{contextHelp}</div>
              ) : (
                messages.map((message, index) => (
                  <article
                    key={`message-${index}`}
                    className={`assistant__message assistant__message--${message.role}`}
                  >
                    <header>{message.role === 'user' ? 'You' : 'Guardrails AI'}</header>
                    <pre>{message.content}</pre>
                  </article>
                ))
              )}
            </div>

            <form className="assistant__prompt" onSubmit={handleSubmit}>
              <label htmlFor="prompt">Ask Guardrails AI</label>
              <textarea
                id="prompt"
                placeholder="Ask how to guard a model, build a flow, or integrate approvals in controllers."
                value={input}
                onChange={(event) => setInput(event.target.value)}
                rows={4}
              />
              <div className="assistant__actions">
                <button type="submit" className="assistant__send" disabled={loading}>
                  <Send size={16} aria-hidden="true" /> {loading ? 'Thinking…' : 'Send'}
                </button>
                <button type="button" className="assistant__secondary" onClick={clearConversation} disabled={loading || messages.length === 0}>
                  <RefreshCw size={14} aria-hidden="true" /> Reset chat
                </button>
              </div>
            </form>
          </section>
        </div>

        <footer className="assistant__footer">
          Answers always cite the relevant documentation section so you can verify before shipping changes.
        </footer>
      </div>
    </div>
  );
}
