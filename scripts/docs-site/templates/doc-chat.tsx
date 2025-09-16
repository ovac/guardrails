import React, {useEffect, useMemo, useState} from 'react';
import type {ChangeEvent, FormEvent} from 'react';
import docsIndex from '@site/static/docs-index.json';

const storageKey = 'guardrails-docs-openai-key';

type Section = {
  id: string;
  title: string;
  content: string;
  href: string;
};

type ChatMessage = {
  role: 'user' | 'assistant';
  content: string;
};

type CompletionMessage = {
  role: 'system' | 'user' | 'assistant';
  content: string;
};

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
  const [model, setModel] = useState(defaultModel);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [contextSections, setContextSections] = useState<Section[]>([]);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }
    const savedKey = window.localStorage.getItem(storageKey);
    if (savedKey) {
      setApiKey(savedKey);
    }
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }
    if (apiKey) {
      window.localStorage.setItem(storageKey, apiKey);
    } else {
      window.localStorage.removeItem(storageKey);
    }
  }, [apiKey]);

  const contextHelp = useMemo(() => {
    if (!messages.length) {
      return 'Ask anything about installing, configuring, or extending Guardrails.';
    }
    return undefined;
  }, [messages.length]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    const trimmed = input.trim();
    if (!trimmed) {
      return;
    }
    if (!apiKey) {
      setError('Add an OpenAI-compatible API key to start chatting. The key is only stored in your browser.');
      return;
    }

    const userMessage: ChatMessage = {role: 'user', content: trimmed};
    setMessages((prev) => [...prev, userMessage]);
    setInput('');
    setLoading(true);

    const relevantSections = rankSections(trimmed + ' ' + messages.map((message) => message.content).join(' '));
    setContextSections(relevantSections);
    const context = relevantSections
      .map((section) => `### ${section.title}\n${section.content}`)
      .join('\n\n');

    const completionMessages: CompletionMessage[] = [
      {
        role: 'system',
        content:
          'You are GuardrailsAI, a documentation assistant for the ovac/guardrails Laravel package. Answer with concise explanations, cite relevant section titles when possible, and prefer returning runnable PHP examples. Use the provided documentation context verbatim when it answers the question.',
      },
      {
        role: 'system',
        content: `Guardrails documentation context:\n\n${context}`,
      },
      ...messages.map((message) => ({role: message.role, content: message.content} as CompletionMessage)),
      {role: 'user', content: trimmed},
    ];

    try {
      const response = await fetch('https://api.openai.com/v1/chat/completions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${apiKey}`,
        },
        body: JSON.stringify({
          model,
          messages: completionMessages,
          temperature: 0.2,
          max_tokens: 800,
        }),
      });

      if (!response.ok) {
        const details = await response.json().catch(() => ({}));
        throw new Error(details.error?.message || `API request failed with status ${response.status}`);
      }

      const payload = await response.json();
      const content: string | undefined = payload?.choices?.[0]?.message?.content;
      if (!content) {
        throw new Error('No content returned by the model.');
      }
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

  return (
    <div className="chatCard">
      <div>
        <label htmlFor="apiKey">OpenAI-compatible API key</label>
        <input
          id="apiKey"
          type="password"
          placeholder="sk-..."
          value={apiKey}
          onChange={(event) => setApiKey(event.target.value)}
        />
        <small>
          Keys are stored locally in your browser. Use OpenAI, OpenRouter, or compatible providers that implement the chat completions
          endpoint.
        </small>
      </div>
      <div>
        <label htmlFor="model">Model</label>
        <select id="model" value={model} onChange={(event: ChangeEvent<HTMLSelectElement>) => setModel(event.target.value)}>
          <option value="gpt-4o-mini">gpt-4o-mini</option>
          <option value="gpt-4o">gpt-4o</option>
          <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
        </select>
      </div>
      <div className="chatMessages">
        {messages.length === 0 && <p>{contextHelp}</p>}
        {messages.map((message, index) => (
          <div key={`message-${index}`} className={`chatMessage ${message.role}`}>
            <strong>{message.role === 'user' ? 'You' : 'Guardrails AI'}</strong>
            <pre>{message.content}</pre>
          </div>
        ))}
      </div>
      {contextSections.length > 0 && (
        <div className="alert alert--secondary" role="note">
          <strong>Context from docs:</strong>
          <ul>
            {contextSections.map((section) => (
              <li key={section.id}>
                <a href={section.href} target="_blank" rel="noreferrer">{section.title}</a>
              </li>
            ))}
          </ul>
        </div>
      )}
      {error && (
        <div className="alert alert--danger" role="alert">
          {error}
        </div>
      )}
      <form className="chatControls" onSubmit={handleSubmit}>
        <label htmlFor="prompt" style={{flex: 1}}>
          <textarea
            id="prompt"
            placeholder="Ask how to guard a model, build a flow, or integrate approvals in controllers."
            value={input}
            onChange={(event) => setInput(event.target.value)}
          />
        </label>
        <div style={{display: 'flex', flexDirection: 'column', gap: '0.5rem'}}>
          <button className="button button--primary" type="submit" disabled={loading}>
            {loading ? 'Thinking…' : 'Send'}
          </button>
          <button className="button button--secondary" type="button" onClick={clearConversation} disabled={loading || messages.length === 0}>
            Reset chat
          </button>
        </div>
      </form>
    </div>
  );
}
