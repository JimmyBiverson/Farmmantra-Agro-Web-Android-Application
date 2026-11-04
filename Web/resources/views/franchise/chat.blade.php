@extends('layouts.app')
@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
@php
    $activeConvoId = request('convo');
    $activeConvo = $activeConvoId ? \App\Models\Conversation::with(['messages.sender', 'creator'])->find($activeConvoId) : null;
    $showNew = request('new');
@endphp

<div class="card-full overflow-hidden" style="height: calc(100vh - 180px); max-height: calc(100vh - 180px);">
    <div class="flex h-full" x-data="{ showSidebar: true }">
        {{-- Conversations Sidebar --}}
        <div class="w-full sm:w-72 flex-shrink-0 border-r flex flex-col transition-all duration-300"
             :class="{ 'hidden sm:flex': !showSidebar, 'absolute sm:relative inset-0 z-20 sm:z-auto bg-[var(--bg-card-solid)] sm:bg-transparent': showSidebar }"
             style="border-color:var(--border-color)">
            <div class="p-3 flex items-center gap-2" style="border-bottom:1px solid var(--border-color)">
                <button @click="showSidebar = false" class="sm:hidden p-1.5 rounded-lg" style="color:var(--text-muted)">
                    <i class="fas fa-arrow-left text-sm"></i>
                </button>
                <h3 class="text-sm font-semibold flex-1" style="color:var(--text-primary)">Conversations</h3>
                <a href="{{ route('web.franchise.chat') }}?new=1" class="p-1.5 rounded-lg text-xs gradient-indigo text-white hover:opacity-90 transition" title="New Conversation">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            <div class="flex-1 overflow-y-auto">
                @forelse($conversations as $c)
                <a href="{{ route('web.franchise.chat', ['convo' => $c->id]) }}"
                   class="block px-4 py-3 border-b transition hover:opacity-80 {{ $activeConvoId == $c->id ? 'border-l-2' : '' }}"
                   style="border-color:var(--border-color); {{ $activeConvoId == $c->id ? 'background:var(--accent-light); border-left-color:var(--accent-color)' : '' }}">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background: var(--accent-color)">
                            {{ strtoupper(substr($c->creator?->name ?? 'S', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium truncate" style="color:var(--text-primary)">{{ $c->subject }}</p>
                            <p class="text-xs mt-0.5 truncate" style="color:var(--text-muted)">{{ $c->latestMessage?->message ?? 'No messages yet' }}</p>
                        </div>
                    </div>
                    <p class="text-xs mt-1" style="color:var(--text-muted)">{{ $c->updated_at->diffForHumans() }}</p>
                </a>
                @empty
                <div class="p-4 text-center text-xs" style="color:var(--text-muted)">
                    <i class="fas fa-comments text-3xl mb-2 block" style="color:var(--border-color)"></i>
                    <p>No conversations yet</p>
                    <p class="mt-1">Messages with Farmmantra staff will appear here</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Chat Area --}}
        <div class="flex-1 flex flex-col min-w-0">
            @if($showNew)
                {{-- New Conversation Form --}}
                <div class="flex items-center gap-3 px-4 py-3" style="border-bottom:1px solid var(--border-color)">
                    <button @click="showSidebar = true" class="sm:hidden p-1.5 rounded-lg" style="color:var(--text-muted)">
                        <i class="fas fa-bars text-sm"></i>
                    </button>
                    <h3 class="text-sm font-semibold" style="color:var(--text-primary)">New Conversation</h3>
                </div>
                <div class="flex-1 overflow-y-auto p-4">
                    <form action="{{ route('web.franchise.chat.create') }}" method="POST" class="space-y-4 max-w-lg">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color:var(--text-secondary)">Subject</label>
                            <input type="text" name="subject" required placeholder="What's this about?"
                                   class="w-full rounded-lg border px-3 py-2.5 text-sm" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color:var(--text-secondary)">Message</label>
                            <textarea name="message" rows="4" required placeholder="Write your message..."
                                      class="w-full rounded-lg border px-3 py-2.5 text-sm resize-none" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('web.franchise.chat') }}" class="px-4 py-2 rounded-lg text-sm font-medium border transition hover:opacity-80" style="border-color:var(--border-color); color:var(--text-secondary)">Cancel</a>
                            <button type="submit" class="px-5 py-2 gradient-indigo text-white rounded-lg text-sm font-semibold hover:opacity-90 transition">
                                <i class="fas fa-paper-plane mr-1"></i> Send
                            </button>
                        </div>
                    </form>
                </div>

            @elseif($activeConvo)
                {{-- Conversation Header --}}
                <div class="flex items-center gap-3 px-4 py-3" style="border-bottom:1px solid var(--border-color)">
                    <button @click="showSidebar = true" class="sm:hidden p-1.5 rounded-lg" style="color:var(--text-muted)">
                        <i class="fas fa-bars text-sm"></i>
                    </button>
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background: var(--accent-color)">
                        {{ strtoupper(substr($activeConvo->creator?->name ?? 'S', 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold" style="color:var(--text-primary)">{{ $activeConvo->subject }}</h3>
                        <p class="text-xs" style="color:var(--text-muted)">Started {{ $activeConvo->created_at->diffForHumans() }}</p>
                    </div>
                </div>

                {{-- Messages --}}
                <div class="flex-1 overflow-y-auto p-4 space-y-3" id="chatMessages">
                    @forelse($activeConvo->messages as $msg)
                    <div class="flex {{ $msg->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-md rounded-xl px-4 py-2.5 {{ $msg->sender_id === auth()->id() ? 'gradient-indigo text-white' : '' }}"
                             style="{{ $msg->sender_id !== auth()->id() ? 'background:var(--bg-input); color:var(--text-primary)' : '' }}">
                            <p class="text-xs font-semibold mb-0.5 {{ $msg->sender_id === auth()->id() ? 'text-white/80' : '' }}"
                               style="{{ $msg->sender_id !== auth()->id() ? 'color:var(--text-muted)' : '' }}">{{ $msg->sender?->name }}</p>
                            <p class="text-sm leading-relaxed">{{ $msg->message }}</p>
                            <p class="text-xs mt-1 {{ $msg->sender_id === auth()->id() ? 'text-white/60' : '' }}"
                               style="{{ $msg->sender_id !== auth()->id() ? 'color:var(--text-muted)' : '' }}">{{ $msg->created_at->format('H:i') }}</p>
                        </div>
                    </div>
                    @empty
                    <p class="text-center text-xs py-12" style="color:var(--text-muted)">
                        <i class="fas fa-comment-dots text-2xl mb-2 block" style="color:var(--border-color)"></i>
                        No messages yet. Say something!
                    </p>
                    @endforelse
                </div>

                {{-- Send Message Form --}}
                <div class="p-3" style="border-top:1px solid var(--border-color)">
                    <form action="{{ route('web.franchise.chat.send') }}" method="POST" class="flex gap-2">
                        @csrf
                        <input type="hidden" name="conversation_id" value="{{ $activeConvo->id }}">
                        <input type="text" name="message" required placeholder="Type a message..." autocomplete="off"
                               class="flex-1 rounded-lg border px-3 py-2.5 text-sm min-w-0" style="background:var(--bg-input); border-color:var(--border-color); color:var(--text-primary)">
                        <button type="submit" class="px-4 py-2.5 gradient-indigo text-white rounded-lg text-sm font-semibold hover:opacity-90 transition flex-shrink-0">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>

            @else
                {{-- Empty State --}}
                <div class="flex items-center gap-3 px-4 py-3 sm:hidden" style="border-bottom:1px solid var(--border-color)">
                    <button @click="showSidebar = true" class="p-1.5 rounded-lg" style="color:var(--text-muted)">
                        <i class="fas fa-bars text-sm"></i>
                    </button>
                    <span class="text-sm font-semibold" style="color:var(--text-primary)">Messages</span>
                </div>
                <div class="flex-1 flex items-center justify-center" style="color:var(--text-muted)">
                    <div class="text-center px-4">
                        <i class="fas fa-paper-plane text-4xl mb-3" style="color:var(--border-color)"></i>
                        <p class="text-sm font-medium">Select a conversation</p>
                        <p class="text-xs mt-1">Start chatting with your Farmmantra support team</p>
                        <a href="{{ route('web.franchise.chat') }}?new=1" class="inline-block mt-3 px-4 py-2 gradient-indigo text-white rounded-lg text-xs font-semibold hover:opacity-90 transition">
                            <i class="fas fa-plus mr-1"></i> New Conversation
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatBox = document.getElementById('chatMessages');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    });
</script>
@endsection