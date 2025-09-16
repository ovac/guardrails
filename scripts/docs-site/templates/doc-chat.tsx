import React, {useEffect, useMemo, useState} from 'react';
import type {ChangeEvent, FormEvent} from 'react';
import {Key, Send, RefreshCw, FileText, Cpu} from 'lucide-react';
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

  return (
    <div className="max-w-5xl mx-auto p-6">
      <div className="bg-white/80 dark:bg-gray-900/80 backdrop-blur rounded-2xl shadow-lg overflow-hidden border border-gray-100 dark:border-gray-800">
        <header className="flex items-center justify-between gap-4 p-6">
          <div className="flex items-center gap-4">
            <FileText className="w-8 h-8 text-red-600" />
            <div>
              <h1 className="text-xl font-semibold">Guardrails AI Assistant</h1>
              <p className="text-sm text-gray-600 dark:text-gray-300">Connect your OpenAI/OpenRouter-compatible key — it stays in your browser.</p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <label className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
              <Cpu className="w-4 h-4" />
              <select className="bg-transparent outline-none" value={model} onChange={(e) => setModel(e.target.value)} aria-label="Model">
                <option value="gpt-4o-mini">gpt-4o-mini</option>
                <option value="gpt-4o">gpt-4o</option>
                <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
              </select>
            </label>
            <div className="text-xs text-gray-500">Model</div>
          </div>
        </header>

        <main className="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
          {/* Controls column */}
          <section className="md:col-span-1 flex flex-col gap-4">
            <div className="flex flex-col gap-1">
              <label htmlFor="apiKey" className="text-sm font-medium text-gray-700 dark:text-gray-200">API key</label>
              <div className="flex items-center gap-2">
                <div className="relative flex-1">
                  <input
                    id="apiKey"
                    type={showKey ? 'text' : 'password'}
                    placeholder="sk-..."
                    value={apiKey}
                    onChange={(event) => setApiKey(event.target.value)}
                    className="w-full px-3 py-2 rounded-md border border-gray-200 dark:border-gray-800 focus:outline-none focus:ring-2 focus:ring-red-200 dark:focus:ring-red-900"
                  />
                </div>
                <button
                  type="button"
                  onClick={() => setShowKey((s) => !s)}
                  aria-label={showKey ? 'Hide key' : 'Show key'}
                  className="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                  <Key className="w-4 h-4" />
                </button>
              </div>
              <p className="text-xs text-gray-500">Keys are stored locally in your browser. Use OpenAI, OpenRouter, or compatible providers.</p>
            </div>

            <div className="bg-gray-50 dark:bg-gray-800 p-3 rounded-md border border-gray-100 dark:border-gray-800">
              <strong className="text-sm">Context hint</strong>
              <p className="mt-2 text-xs text-gray-600 dark:text-gray-300">{contextHelp ?? 'Conversation in progress — we will pull relevant doc sections.'}</p>
            </div>

            {contextSections.length > 0 && (
              <div className="p-3 rounded-md border border-gray-100 dark:border-gray-800 bg-white/60">
                <div className="flex items-center gap-2 text-sm font-medium mb-2">Context from docs</div>
                <ul className="text-sm list-disc pl-5 space-y-1">
                  {contextSections.map((section) => (
                    <li key={section.id}>
                      <a href={section.href} target="_blank" rel="noreferrer" className="text-red-600 hover:underline">{section.title}</a>
                    </li>
                  ))}
                </ul>
              </div>
            )}

            {error && (
              <div className="rounded-md p-3 bg-red-50 border border-red-100 text-sm text-red-800">{error}</div>
            )}

            <div className="mt-auto flex gap-2">
              <button className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-md bg-gray-100 hover:bg-gray-200 text-sm" onClick={() => { navigator.clipboard?.writeText(contextSections.map(s => s.href).join('\n')); }}>
                <RefreshCw className="w-4 h-4" /> Copy context links
              </button>
              <button className="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-white border border-gray-200 hover:shadow text-sm" onClick={clearConversation} disabled={loading || messages.length === 0}>
                <RefreshCw className="w-4 h-4" /> Reset
              </button>
            </div>
          </section>

          {/* Chat column */}
          <section className="md:col-span-2 flex flex-col gap-4 h-[480px]">
            <div className="flex-1 overflow-auto p-4 bg-white rounded-md border border-gray-100 dark:bg-gray-900 dark:border-gray-800">
              {messages.length === 0 ? (
                <div className="text-center text-gray-500 py-24">{contextHelp}</div>
              ) : (
                <div className="flex flex-col gap-4">
                  {messages.map((message, index) => (
                    <div key={`message-${index}`} className={`p-3 rounded-lg max-w-[90%] ${message.role === 'user' ? 'self-end bg-red-50 text-gray-900' : 'self-start bg-gray-100 dark:bg-gray-800 text-gray-900'}`}>
                      <div className="text-xs font-semibold mb-1">{message.role === 'user' ? 'You' : 'Guardrails AI'}</div>
                      <pre className="whitespace-pre-wrap text-sm">{message.content}</pre>
                    </div>
                  ))}
                </div>
              )}
            </div>

            <form className="flex gap-3 items-start" onSubmit={handleSubmit}>
              <label htmlFor="prompt" className="flex-1">
                <textarea
                  id="prompt"
                  placeholder="Ask how to guard a model, build a flow, or integrate approvals in controllers."
                  value={input}
                  onChange={(event) => setInput(event.target.value)}
                  className="w-full p-3 rounded-md border border-gray-200 dark:border-gray-800 focus:outline-none focus:ring-2 focus:ring-red-200 dark:focus:ring-red-900 resize-none h-20"
                />
              </label>

              <div className="flex flex-col gap-2 w-36">
                <button className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700" type="submit" disabled={loading}>
                  <Send className="w-4 h-4" /> {loading ? 'Thinking…' : 'Send'}
                </button>
                <button className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-md border border-gray-200 bg-white" type="button" onClick={clearConversation} disabled={loading || messages.length === 0}>
                  <RefreshCw className="w-4 h-4" /> Reset chat
                </button>
              </div>
            </form>
          </section>
        </main>

        <footer className="p-4 text-xs text-gray-500 border-t border-gray-100 dark:border-gray-800">
          Answers include the most relevant doc sections so you can verify before copying into production.
        </footer>
      </div>
    </div>
  );
}
