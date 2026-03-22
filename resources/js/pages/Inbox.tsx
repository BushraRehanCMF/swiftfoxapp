import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';

type Contact = {
  id: number | string;
  name?: string | null;
  phone_number?: string | null;
};

type Message = {
  id: number | string;
  conversation_id: number | string;
  direction: 'inbound' | 'outbound';
  content: string;
  status: string;
  created_at: string;
  whatsapp_message_id?: string | null;
};

type Conversation = {
  id: number | string;
  status: string;
  last_message_at?: string | null;
  contact?: Contact | null;
  last_message?: Message | null;
  messages?: Message[];
  labels?: { id: number | string; name: string; color: string }[];
};

type ApiCollection<T> = {
  data: T[];
};

type ApiResource<T> = {
  data: T;
};

const formatTimestamp = (value?: string | null) => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString();
};

const Inbox: React.FC = () => {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [selectedId, setSelectedId] = useState<number | string | null>(null);
  const [activeConversation, setActiveConversation] = useState<Conversation | null>(null);
  const [messageText, setMessageText] = useState('');
  const [loadingList, setLoadingList] = useState(true);
  const [loadingConversation, setLoadingConversation] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');

  const selectedConversation = useMemo(() => {
    if (!selectedId) return null;
    return conversations.find((conversation) => conversation.id === selectedId) || null;
  }, [conversations, selectedId]);

  const fetchConversations = async () => {
    setLoadingList(true);
    setError('');
    try {
      const { data } = await api.get<ApiCollection<Conversation>>('/conversations');
      const items = data.data || [];
      setConversations(items);
      if (!selectedId && items.length > 0) {
        setSelectedId(items[0].id);
      }
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load inbox.');
    } finally {
      setLoadingList(false);
    }
  };

  const fetchConversation = async (conversationId: number | string) => {
    setLoadingConversation(true);
    setError('');
    try {
      const { data } = await api.get<ApiResource<Conversation>>(`/conversations/${conversationId}`);
      setActiveConversation(data.data);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load conversation.');
    } finally {
      setLoadingConversation(false);
    }
  };

  const handleSend = async () => {
    const trimmed = messageText.trim();
    if (!trimmed || !selectedId) return;
    setSending(true);
    setError('');
    try {
      const { data } = await api.post<ApiResource<Message>>(
        `/conversations/${selectedId}/messages`,
        { content: trimmed }
      );

      setMessageText('');

      const newMessage = data.data;
      setActiveConversation((prev) => {
        if (!prev) return prev;
        const updated = {
          ...prev,
          messages: [...(prev.messages || []), newMessage],
          last_message: newMessage,
          last_message_at: newMessage.created_at,
        };
        return updated;
      });

      setConversations((prev) =>
        prev.map((conversation) =>
          conversation.id === selectedId
            ? {
                ...conversation,
                last_message: newMessage,
                last_message_at: newMessage.created_at,
              }
            : conversation
        )
      );
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to send message.');
    } finally {
      setSending(false);
    }
  };

  useEffect(() => {
    fetchConversations();
  }, []);

  useEffect(() => {
    if (selectedId) {
      fetchConversation(selectedId);
    } else {
      setActiveConversation(null);
    }
  }, [selectedId]);

  return (
    <div className="flex h-full gap-6">
      <section className="w-80 shrink-0 rounded-lg border border-gray-200 bg-white shadow-sm">
        <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3">
          <div>
            <h1 className="text-lg font-semibold text-gray-900">Inbox</h1>
            <p className="text-xs text-gray-500">Incoming conversations</p>
          </div>
          <button
            className="text-xs font-medium text-emerald-700 hover:text-emerald-800"
            onClick={fetchConversations}
            disabled={loadingList}
          >
            Refresh
          </button>
        </div>

        {loadingList ? (
          <div className="px-4 py-6 text-sm text-gray-500">Loading conversations...</div>
        ) : conversations.length === 0 ? (
          <div className="px-4 py-6 text-sm text-gray-500">
            No conversations yet. Once messages arrive, they will appear here.
          </div>
        ) : (
          <div className="max-h-[calc(100vh-220px)] overflow-y-auto">
            {conversations.map((conversation) => {
              const contactName = conversation.contact?.name || conversation.contact?.phone_number || 'Unknown';
              const preview = conversation.last_message?.content || 'No messages yet';
              const isActive = conversation.id === selectedId;
              return (
                <button
                  key={conversation.id}
                  className={`w-full border-b border-gray-100 px-4 py-3 text-left hover:bg-emerald-50 ${
                    isActive ? 'bg-emerald-50' : ''
                  }`}
                  onClick={() => setSelectedId(conversation.id)}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="text-sm font-semibold text-gray-900 truncate">{contactName}</span>
                      {/* Show label color badges */}
                      {conversation.labels && conversation.labels.length > 0 && (
                        <span className="flex gap-1">
                          {conversation.labels.map((label) => (
                            <span
                              key={label.id}
                              className="inline-block h-3 w-3 rounded-full border border-white shadow"
                              style={{ backgroundColor: label.color || '#059669' }}
                              title={label.name}
                            />
                          ))}
                        </span>
                      )}
                    </div>
                    <div className="text-xs text-gray-400">
                      {formatTimestamp(conversation.last_message_at)}
                    </div>
                  </div>
                  <div className="mt-1 text-xs text-gray-500 line-clamp-1">{preview}</div>
                </button>
              );
            })}
          </div>
        )}
      </section>

      <section className="flex-1 rounded-lg border border-gray-200 bg-white shadow-sm">
        <div className="border-b border-gray-200 px-6 py-4">
          <div className="flex items-center justify-between">
            <div>
              <div className="text-sm text-gray-500">Conversation</div>
              <div className="flex items-center gap-2">
                <span className="text-lg font-semibold text-gray-900">
                  {selectedConversation?.contact?.name ||
                    selectedConversation?.contact?.phone_number ||
                    'Select a conversation'}
                </span>
                {/* Render conversation labels as badges */}
                {selectedConversation?.labels && selectedConversation.labels.length > 0 && (
                  <div className="flex gap-1">
                    {selectedConversation.labels.map((label) => (
                      <span
                        key={label.id}
                        className="inline-block rounded px-2 py-0.5 text-xs font-medium text-white"
                        style={{ backgroundColor: label.color || '#059669' }}
                        title={label.name}
                      >
                        {label.name}
                      </span>
                    ))}
                  </div>
                )}
              </div>
            </div>
            {selectedConversation && (
              <div className="text-xs text-gray-500">
                Status: <span className="font-medium text-gray-700">{selectedConversation.status}</span>
              </div>
            )}
          </div>
        </div>

        {error && (
          <div className="mx-6 mt-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
            {error}
          </div>
        )}

        <div className="flex h-[calc(100vh-280px)] flex-col">
          <div className="flex-1 overflow-y-auto px-6 py-4">
            {loadingConversation ? (
              <div className="text-sm text-gray-500">Loading messages...</div>
            ) : !selectedConversation ? (
              <div className="text-sm text-gray-500">Choose a conversation from the left.</div>
            ) : (activeConversation?.messages || []).length === 0 ? (
              <div className="text-sm text-gray-500">No messages in this conversation yet.</div>
            ) : (
              <div className="space-y-3">
                {(activeConversation?.messages || []).map((message) => (
                  <div
                    key={message.id}
                    className={`flex ${message.direction === 'outbound' ? 'justify-end' : 'justify-start'}`}
                  >
                    <div
                      className={`max-w-[70%] rounded-lg px-3 py-2 text-sm shadow-sm ${
                        message.direction === 'outbound'
                          ? 'bg-emerald-600 text-white'
                          : 'bg-gray-100 text-gray-900'
                      }`}
                    >
                      <div>{message.content}</div>
                      <div
                        className={`mt-1 text-[11px] ${
                          message.direction === 'outbound' ? 'text-emerald-100' : 'text-gray-400'
                        }`}
                      >
                        {formatTimestamp(message.created_at)}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="border-t border-gray-200 px-6 py-4">
            <div className="flex items-center gap-3">
              <input
                type="text"
                className="flex-1 rounded border border-gray-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none"
                placeholder={selectedConversation ? 'Type a reply...' : 'Select a conversation first'}
                value={messageText}
                onChange={(event) => setMessageText(event.target.value)}
                disabled={!selectedConversation || sending}
              />
              <button
                className="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-60"
                onClick={handleSend}
                disabled={!selectedConversation || sending || !messageText.trim()}
              >
                {sending ? 'Sending...' : 'Send'}
              </button>
            </div>
            {selectedConversation && (
              <div className="mt-2 text-xs text-gray-400">
                Messages are sent within the 24-hour customer care window.
              </div>
            )}
          </div>
        </div>
      </section>
    </div>
  );
};

export default Inbox;
