@extends('layouts.app')
@section('title', 'Support Chat')
@section('page-title', 'Live Support Chat')

@section('content')
<div class="card-full overflow-hidden" style="height: calc(100vh - 180px); max-height: calc(100vh - 180px);">
    <div class="flex h-full" x-data="{ showSidebar: true }">
        {{-- Conversations Sidebar --}}
        <div class="w-full sm:w-80 flex-shrink-0 border-r flex flex-col transition-all duration-300"
             :class="{ 'hidden sm:flex': !showSidebar, 'absolute sm:relative inset-0 z-20 sm:z-auto bg-[var(--bg-card-solid)] sm:bg-transparent': showSidebar }"
             style="border-color:var(--border-color)">
            <div class="p-3.5 flex items-center justify-between" style="border-bottom:1px solid var(--border-color)">
                <div class="flex items-center gap-2">
                    <button @click="showSidebar = false" class="sm:hidden p-1.5 rounded-lg" style="color:var(--text-muted)">
                        <i class="fas fa-arrow-left text-sm"></i>
                    </button>
                    <h3 class="text-sm font-bold flex-1" style="color:var(--text-primary)">Conversations</h3>
                </div>
                <span class="badge badge-indigo text-xs px-2 py-0.5 rounded-full">{{ $conversations->count() }} active</span>
            </div>
            <div class="flex-1 overflow-y-auto">
                @forelse($conversations as $c)
                <a href="{{ route('web.admin.chat', ['conversation_id' => $c->id]) }}"
                   class="block px-4 py-3 border-b transition hover:opacity-80 {{ $activeConversation && $activeConversation->id == $c->id ? 'border-l-4 border-l-indigo-600' : '' }}"
                   style="border-color:var(--border-color); {{ $activeConversation && $activeConversation->id == $c->id ? 'background:var(--accent-light);' : '' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0 gradient-indigo">
                            {{ strtoupper(substr($c->creator?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-1">
                                <p class="text-sm font-semibold truncate" style="color:var(--text-primary)">{{ $c->creator?->name ?? 'User' }}</p>
                                <span class="text-[10px]" style="color:var(--text-muted)">{{ $c->updated_at->diffForHumans(null, true) }}</span>
                            </div>
                            <p class="text-xs font-medium truncate mt-0.5" style="color:var(--text-secondary)">{{ $c->subject }}</p>
                            <p class="text-xs truncate mt-0.5" style="color:var(--text-muted)">
                                {{ $c->latestMessage?->message ?? 'No messages yet' }}
                            </p>
                        </div>
                    </div>
                </a>
                @empty
                <div class="p-6 text-center text-xs" style="color:var(--text-muted)">
                    <i class="fas fa-comments text-4xl mb-3 block" style="color:var(--border-color)"></i>
                    <p class="font-semibold">No active chats</p>
                    <p class="mt-1">Support tickets and mobile messages will appear here</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Chat Area --}}
        <div class="flex-1 flex flex-col min-w-0">
            @if($activeConversation)
                {{-- Conversation Header --}}
                <div class="flex items-center justify-between px-4 py-3" style="border-bottom:1px solid var(--border-color)">
                    <div class="flex items-center gap-3">
                        <button @click="showSidebar = true" class="sm:hidden p-1.5 rounded-lg" style="color:var(--text-muted)">
                            <i class="fas fa-bars text-sm"></i>
                        </button>
                        <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0 gradient-indigo">
                            {{ strtoupper(substr($activeConversation->creator?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <h3 class="text-sm font-bold" style="color:var(--text-primary)">{{ $activeConversation->creator?->name ?? 'User' }}</h3>
                            <p class="text-xs" style="color:var(--text-muted)">
                                Subject: <span class="font-medium" style="color:var(--text-secondary)">{{ $activeConversation->subject }}</span>
                                @if($activeConversation->franchise)
                                    &bull; Franchise: <span class="font-medium text-emerald-600">{{ $activeConversation->franchise->name }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-emerald text-xs px-2.5 py-1 rounded-full"><i class="fas fa-circle text-[8px] mr-1"></i> Live</span>
                    </div>
                </div>

                {{-- Messages List --}}
                <div class="flex-1 overflow-y-auto p-4 space-y-3.5" id="chatMessages">
                    @forelse($activeConversation->messages as $msg)
                    @php($isAdminMessage = $msg->sender_id === auth()->id() || $msg->sender?->role?->name === 'System Administrator')
                    <div class="flex {{ $isAdminMessage ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-md rounded-2xl px-4 py-2.5 shadow-sm {{ $isAdminMessage ? 'gradient-indigo text-white' : 'cursor-pointer hover:ring-2 hover:ring-indigo-400' }}"
                             data-message-id="{{ $msg->id }}" data-message-text="{{ e($msg->message) }}" data-is-incoming="{{ $isAdminMessage ? 'false' : 'true' }}"
                             title="{{ $isAdminMessage ? '' : 'Click to reply to this message' }}"
                             style="{{ !$isAdminMessage ? 'background:var(--bg-input); color:var(--text-primary); border: 1px solid var(--border-color);' : '' }}">
                            <div class="flex items-center justify-between gap-3 mb-1">
                                <span class="text-xs font-bold {{ $msg->sender_id === auth()->id() || $msg->sender?->role?->name === 'System Administrator' ? 'text-white/90' : '' }}"
                                      style="{{ $msg->sender_id !== auth()->id() && $msg->sender?->role?->name !== 'System Administrator' ? 'color:var(--accent-color);' : '' }}">
                                    {{ $msg->sender?->name ?? 'User' }}
                                </span>
                                <span class="text-[10px] {{ $msg->sender_id === auth()->id() || $msg->sender?->role?->name === 'System Administrator' ? 'text-white/70' : '' }}"
                                      style="{{ $msg->sender_id !== auth()->id() && $msg->sender?->role?->name !== 'System Administrator' ? 'color:var(--text-muted);' : '' }}">
                                    {{ $msg->created_at->format('h:i A') }}
                                </span>
                            </div>
                            @if($msg->replyTo)
                                <div class="mb-2 border-l-2 border-white/50 pl-2 text-xs opacity-80">Replying to {{ $msg->replyTo->sender?->name ?? 'User' }}: {{ $msg->replyTo->message }}</div>
                            @endif
                            <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $msg->message }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-xs py-12" style="color:var(--text-muted)">
                        <i class="fas fa-comment-dots text-3xl mb-2 block" style="color:var(--border-color)"></i>
                        No messages yet in this conversation.
                    </p>
                    @endforelse
                </div>

                {{-- Send Message Form --}}
                <div class="p-3" style="border-top:1px solid var(--border-color)">
                    <div id="adminReplyContext" class="hidden mb-2 rounded-lg px-3 py-2 text-xs" style="background:var(--accent-light); color:var(--text-secondary)">
                        <div class="flex items-center justify-between gap-2">
                            <span>Replying to <strong id="adminReplySender">User</strong>: <span id="adminReplyText"></span></span>
                            <button type="button" id="clearAdminReply" class="text-xs font-semibold" style="color:var(--accent-color)">Cancel</button>
                        </div>
                    </div>
                    <form id="adminChatForm" action="{{ route('web.admin.chat.send', ['id' => $activeConversation->id]) }}" method="POST" class="flex gap-2">
                        @csrf
                        <input type="hidden" id="adminReplyToMessageId" name="reply_to_message_id">
                        <textarea id="adminChatMessageInput" name="message" required rows="1" placeholder="Type reply to {{ $activeConversation->creator?->name ?? 'user' }} or click a message to answer it..." autocomplete="off"
                                  class="flex-1 rounded-xl border px-4 py-2.5 text-sm min-w-0 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"></textarea>
                        <button type="submit" class="px-5 py-2.5 gradient-indigo text-white rounded-xl text-sm font-semibold hover:opacity-90 transition flex-shrink-0 flex items-center gap-1.5 shadow-md">
                            <span>Send</span>
                            <i class="fas fa-paper-plane text-xs"></i>
                        </button>
                    </form>
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex-1 flex items-center justify-center" style="color:var(--text-muted)">
                    <div class="text-center px-4 max-w-sm">
                        <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center gradient-indigo text-white shadow-lg">
                            <i class="fas fa-comments text-2xl"></i>
                        </div>
                        <p class="text-base font-bold" style="color:var(--text-primary)">Admin Live Support</p>
                        <p class="text-xs mt-1 leading-relaxed">Select an active conversation from the sidebar to start communicating with mobile app users and franchise partners.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@if($activeConversation)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatBox = document.getElementById('chatMessages');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;

        const chatForm = document.getElementById('adminChatForm');
        const messageInput = document.getElementById('adminChatMessageInput');
        const replyContext = document.getElementById('adminReplyContext');
        const replyToInput = document.getElementById('adminReplyToMessageId');
        const replySender = document.getElementById('adminReplySender');
        const replyText = document.getElementById('adminReplyText');
        let selectedMessageId = null;

        function selectMessage(messageId, senderName, message) {
            selectedMessageId = String(messageId);
            replyToInput.value = selectedMessageId;
            replySender.textContent = senderName;
            replyText.textContent = message;
            replyContext.classList.remove('hidden');
            messageInput.placeholder = 'Write your reply...';
            messageInput.focus();
        }

        function clearReply() {
            selectedMessageId = null;
            replyToInput.value = '';
            replyContext.classList.add('hidden');
            messageInput.placeholder = 'Type reply to {{ $activeConversation->creator?->name ?? 'user' }} or click a message to answer it...';
        }

        function bindMessageSelection() {
            chatBox.querySelectorAll('[data-is-incoming="true"]').forEach(function(message) {
                message.addEventListener('click', function() {
                    selectMessage(message.dataset.messageId, message.querySelector('.text-xs.font-bold').textContent.trim(), message.dataset.messageText);
                });
            });
        }

        bindMessageSelection();
        document.getElementById('clearAdminReply').addEventListener('click', clearReply);

        if (chatForm && messageInput) {
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const text = messageInput.value.trim();
                if (!text) return;

                const formData = new FormData(chatForm);
                fetch(chatForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: formData
                })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.message || 'Unable to send message.');
                    return data;
                })
                .then(data => {
                    if (data.status === 'success') {
                        messageInput.value = '';
                        clearReply();
                        fetchMessages();
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (typeof showToast === 'function') showToast(err.message, 'danger');
                });
            });
        }

        let lastMessageCount = {{ $activeConversation->messages->count() }};
        function fetchMessages() {
            fetch("{{ route('web.admin.chat.messages', ['id' => $activeConversation->id]) }}", {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.messages) {
                    if (data.messages.length > lastMessageCount) {
                        playNotificationChime();
                    }
                    lastMessageCount = data.messages.length;

                    let html = '';
                    data.messages.forEach(msg => {
                        const isSelf = msg.is_admin;
                        const isSelected = selectedMessageId === String(msg.id);
                        const escapedMessage = escapeHtml(msg.message);
                        const escapedSender = escapeHtml(msg.sender_name);
                        const replyMarkup = msg.reply_to ? `<div class="mb-2 border-l-2 ${isSelf ? 'border-white/50' : 'border-indigo-400'} pl-2 text-xs opacity-80">Replying to ${escapeHtml(msg.reply_to.sender_name)}: ${escapeHtml(msg.reply_to.message)}</div>` : '';
                        html += `
                            <div class="flex ${isSelf ? 'justify-end' : 'justify-start'}">
                                <div class="max-w-md rounded-2xl px-4 py-2.5 shadow-sm ${isSelf ? 'gradient-indigo text-white' : ''}"
                                     data-message-id="${msg.id}" data-message-text="${escapeHtml(msg.message)}" data-is-incoming="${isSelf ? 'false' : 'true'}"
                                     title="${isSelf ? '' : 'Click to reply to this message'}" class="${!isSelf ? 'cursor-pointer hover:ring-2 hover:ring-indigo-400' : ''} ${isSelected ? 'ring-2 ring-indigo-500' : ''}"
                                     style="${!isSelf ? 'background:var(--bg-input); color:var(--text-primary); border: 1px solid var(--border-color);' : ''}">
                                    <div class="flex items-center justify-between gap-3 mb-1">
                                        <span class="text-xs font-bold ${isSelf ? 'text-white/90' : ''}" style="${!isSelf ? 'color:var(--accent-color);' : ''}">
                                            ${escapedSender}
                                        </span>
                                        <span class="text-[10px] ${isSelf ? 'text-white/70' : ''}" style="${!isSelf ? 'color:var(--text-muted);' : ''}">
                                            ${msg.created_at}
                                        </span>
                                    </div>
                                    ${replyMarkup}
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap">${escapedMessage}</p>
                                </div>
                            </div>
                        `;
                    });
                    if (chatBox) {
                        const isAtBottom = chatBox.scrollHeight - chatBox.clientHeight <= chatBox.scrollTop + 100;
                        chatBox.innerHTML = html;
                        bindMessageSelection();
                        if (isAtBottom) chatBox.scrollTop = chatBox.scrollHeight;
                    }
                }
            })
            .catch(err => console.error(err));
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>'"]/g, function(character) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[character];
            });
        }

        function playNotificationChime() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
                gain.gain.setValueAtTime(0.1, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.35);
            } catch(e) {}
        }

        setInterval(fetchMessages, 4000);
    });
</script>
@endif
@endsection
