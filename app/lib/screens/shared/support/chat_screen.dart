import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_colors.dart';
import '../../../services/api/api_service.dart';
import '../../../providers/auth_provider.dart';

class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        elevation: 0,
        title: const Text(
          'Help & Support',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(52),
          child: Container(
            decoration: BoxDecoration(color: Colors.black.withAlpha(25)),
            child: TabBar(
              controller: _tabController,
              // White labels/icons so they are clearly visible on the green AppBar
              labelColor: Colors.white,
              unselectedLabelColor: Colors.white60,
              // White pill-shaped indicator
              indicator: BoxDecoration(
                borderRadius: BorderRadius.circular(30),
                color: Colors.white.withAlpha(45),
                border: Border(
                  bottom: BorderSide(color: Colors.white, width: 3),
                ),
              ),
              labelStyle: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
              unselectedLabelStyle: const TextStyle(
                fontWeight: FontWeight.w500,
                fontSize: 13,
              ),
              tabs: const [
                Tab(
                  icon: Icon(Icons.confirmation_number_outlined, size: 18),
                  text: 'Tickets',
                ),
                Tab(
                  icon: Icon(Icons.mark_chat_read_outlined, size: 18),
                  text: 'Live Chat',
                ),
              ],
            ),
          ),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: const [_SupportTicketsTab(), _LiveChatTab()],
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════
// SUPPORT TICKETS TAB
// ════════════════════════════════════════════════════════════════

class _SupportTicketsTab extends StatefulWidget {
  const _SupportTicketsTab();

  @override
  State<_SupportTicketsTab> createState() => _SupportTicketsTabState();
}

class _SupportTicketsTabState extends State<_SupportTicketsTab> {
  List<Map<String, dynamic>> _tickets = [];
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchTickets();
  }

  Future<void> _fetchTickets() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = Provider.of<ApiService>(context, listen: false);
      final data = await api.getConversations();
      if (!mounted) return;
      setState(() {
        _tickets = data;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Failed to load tickets. Tap to retry.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned.fill(child: _buildBody()),
        Positioned(
          right: 16,
          bottom: 16,
          child: FloatingActionButton.extended(
            onPressed: () => _showCreateTicketDialog(context),
            backgroundColor: AppColors.primaryGreen,
            foregroundColor: Colors.white,
            icon: const Icon(Icons.add),
            label: const Text(
              'New Ticket',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading && _tickets.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: GestureDetector(
          onTap: _fetchTickets,
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.wifi_off,
                  size: 48,
                  color: AppColors.textLight,
                ),
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: const TextStyle(color: AppColors.error),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                const Text(
                  'Tap to retry',
                  style: TextStyle(color: AppColors.textSecondary),
                ),
              ],
            ),
          ),
        ),
      );
    }
    if (_tickets.isEmpty) {
      return _buildEmptyTickets();
    }
    return RefreshIndicator(
      onRefresh: _fetchTickets,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 84),
        itemCount: _tickets.length,
        itemBuilder: (context, index) => _buildTicketCard(_tickets[index]),
      ),
    );
  }

  Widget _buildEmptyTickets() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                color: AppColors.primaryGreen.withAlpha(20),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.support_agent,
                size: 52,
                color: AppColors.primaryGreen,
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Need assistance?',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Submit a structured support ticket and our team\nwill get back to you promptly.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.textSecondary, height: 1.5),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: () => _showCreateTicketDialog(context),
              icon: const Icon(Icons.add, color: Colors.white),
              label: const Text(
                'Submit a Ticket',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primaryGreen,
                padding: const EdgeInsets.symmetric(
                  horizontal: 28,
                  vertical: 14,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTicketCard(Map<String, dynamic> ticket) {
    final status = ticket['status'] ?? 'open';
    Color statusColor;
    switch (status) {
      case 'resolved':
        statusColor = AppColors.success;
        break;
      case 'closed':
        statusColor = AppColors.textLight;
        break;
      case 'in_progress':
        statusColor = AppColors.info;
        break;
      default:
        statusColor = AppColors.warning;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: statusColor.withAlpha(25),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(
                    Icons.confirmation_number_outlined,
                    color: statusColor,
                    size: 18,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    ticket['subject'] ?? 'No Subject',
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: statusColor.withAlpha(20),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: statusColor.withAlpha(80)),
                  ),
                  child: Text(
                    status.replaceAll('_', ' ').toUpperCase(),
                    style: TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                      color: statusColor,
                    ),
                  ),
                ),
              ],
            ),
            if (ticket['message'] != null) ...[
              const SizedBox(height: 8),
              Text(
                ticket['message'] as String,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                  height: 1.4,
                ),
              ),
            ],
            if (ticket['admin_response'] != null) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.primaryGreen.withAlpha(15),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: AppColors.primaryGreen.withAlpha(50),
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.support_agent,
                      color: AppColors.primaryGreen,
                      size: 16,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        ticket['admin_response'] as String,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.primaryGreen,
                          height: 1.4,
                        ),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Ref: ${ticket['ticket_number'] ?? '#${ticket['id']}'}',
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppColors.textLight,
                  ),
                ),
                Text(
                  _formatDateTime(ticket['created_at'] as String?),
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppColors.textLight,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _showCreateTicketDialog(BuildContext context) {
    final subjectCtrl = TextEditingController();
    final messageCtrl = TextEditingController();
    String priority = 'normal';
    String category = 'General';
    bool submitting = false;
    final formKey = GlobalKey<FormState>();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModal) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(ctx).viewInsets.bottom,
          ),
          child: Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(24),
                topRight: Radius.circular(24),
              ),
            ),
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(
                            Icons.confirmation_number,
                            color: AppColors.primaryGreen,
                            size: 22,
                          ),
                          const SizedBox(width: 8),
                          const Expanded(
                            child: Text(
                              'Submit Support Ticket',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close),
                            onPressed: () => Navigator.pop(ctx),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: subjectCtrl,
                        decoration: InputDecoration(
                          labelText: 'Subject',
                          hintText: 'e.g. Wrong product delivered',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                          prefixIcon: const Icon(Icons.subject),
                        ),
                        validator: (v) => (v == null || v.trim().isEmpty)
                            ? 'Subject is required'
                            : null,
                        enabled: !submitting,
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              initialValue: category,
                              decoration: InputDecoration(
                                labelText: 'Category',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                              ),
                              items:
                                  [
                                        'General',
                                        'Order',
                                        'Payment',
                                        'Delivery',
                                        'Product',
                                        'Technical',
                                      ]
                                      .map(
                                        (c) => DropdownMenuItem(
                                          value: c,
                                          child: Text(c),
                                        ),
                                      )
                                      .toList(),
                              onChanged: submitting
                                  ? null
                                  : (v) => setModal(() => category = v!),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: DropdownButtonFormField<String>(
                              initialValue: priority,
                              decoration: InputDecoration(
                                labelText: 'Priority',
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                              ),
                              items: const [
                                DropdownMenuItem(
                                  value: 'low',
                                  child: Text('Low'),
                                ),
                                DropdownMenuItem(
                                  value: 'normal',
                                  child: Text('Normal'),
                                ),
                                DropdownMenuItem(
                                  value: 'high',
                                  child: Text('High'),
                                ),
                                DropdownMenuItem(
                                  value: 'urgent',
                                  child: Text('Urgent 🚨'),
                                ),
                              ],
                              onChanged: submitting
                                  ? null
                                  : (v) => setModal(() => priority = v!),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: messageCtrl,
                        maxLines: 4,
                        decoration: InputDecoration(
                          labelText: 'Describe your issue',
                          hintText: 'Provide as much detail as possible...',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                          prefixIcon: const Padding(
                            padding: EdgeInsets.only(bottom: 60),
                            child: Icon(Icons.message_outlined),
                          ),
                        ),
                        validator: (v) => (v == null || v.trim().isEmpty)
                            ? 'Message is required'
                            : null,
                        enabled: !submitting,
                      ),
                      const SizedBox(height: 20),
                      SizedBox(
                        width: double.infinity,
                        height: 50,
                        child: ElevatedButton(
                          onPressed: submitting
                              ? null
                              : () async {
                                  if (!formKey.currentState!.validate()) return;
                                  setModal(() => submitting = true);
                                  try {
                                    final api = Provider.of<ApiService>(
                                      context,
                                      listen: false,
                                    );
                                    await api.createConversation(
                                      subjectCtrl.text.trim(),
                                      messageCtrl.text.trim(),
                                      priority: priority,
                                    );
                                    if (!ctx.mounted) return;
                                    Navigator.pop(ctx);
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(
                                        content: const Text(
                                          '✅ Ticket submitted! We\'ll respond shortly.',
                                        ),
                                        backgroundColor: AppColors.primaryGreen,
                                        behavior: SnackBarBehavior.floating,
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                        ),
                                      ),
                                    );
                                    _fetchTickets();
                                  } catch (e) {
                                    if (!ctx.mounted) return;
                                    setModal(() => submitting = false);
                                    ScaffoldMessenger.of(ctx).showSnackBar(
                                      const SnackBar(
                                        content: Text(
                                          'Failed to submit ticket. Please try again.',
                                        ),
                                        backgroundColor: Colors.red,
                                      ),
                                    );
                                  }
                                },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primaryGreen,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          child: submitting
                              ? const SizedBox(
                                  width: 22,
                                  height: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text(
                                  'Submit Ticket',
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 15,
                                  ),
                                ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  String _formatDateTime(String? dt) {
    if (dt == null) return '';
    final d = DateTime.tryParse(dt);
    if (d == null) return dt;
    return '${d.day}/${d.month}/${d.year} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
  }
}

// ════════════════════════════════════════════════════════════════
// LIVE CHAT TAB — real-time messaging with staff
// ════════════════════════════════════════════════════════════════

class _LiveChatTab extends StatefulWidget {
  const _LiveChatTab();

  @override
  State<_LiveChatTab> createState() => _LiveChatTabState();
}

class _LiveChatTabState extends State<_LiveChatTab> {
  final TextEditingController _msgCtrl = TextEditingController();
  final ScrollController _scrollCtrl = ScrollController();

  Map<String, dynamic>? _activeConversation;
  List<ChatMessage> _messages = [];
  bool _loadingConvs = false;
  List<Map<String, dynamic>> _conversations = [];
  bool _loadingMessages = false;
  bool _sending = false;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _loadConversations();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadConversations() async {
    setState(() => _loadingConvs = true);
    try {
      final api = Provider.of<ApiService>(context, listen: false);
      final data = await api.getConversations();
      if (!mounted) return;
      setState(() {
        _conversations = data;
        _loadingConvs = false;
        // Auto-open if there's an existing conversation
        if (_conversations.isNotEmpty && _activeConversation == null) {
          _openConversation(_conversations.first);
        }
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingConvs = false);
    }
  }

  Future<void> _openConversation(Map<String, dynamic> conv) async {
    _timer?.cancel();
    setState(() {
      _activeConversation = conv;
      _messages = [];
      _loadingMessages = true;
    });
    await _fetchMessages();
    _timer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _fetchMessages(silent: true),
    );
  }

  Future<void> _startNewChat() async {
    final api = Provider.of<ApiService>(context, listen: false);
    try {
      final conv = await api.createConversation(
        'Live Support Session',
        'Hello, I need assistance.',
      );
      if (!mounted) return;
      await _loadConversations();
      if (!mounted) return;
      _openConversation(conv);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Failed to start chat session. Please try again.'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _fetchMessages({bool silent = false}) async {
    if (_activeConversation == null) return;
    final convId = _activeConversation!['id'].toString();
    final myId = Provider.of<AuthProvider>(context, listen: false).user?.id;

    try {
      final api = Provider.of<ApiService>(context, listen: false);
      final full = await api.getConversation(convId);
      if (!mounted || _activeConversation == null) return;

      final rawMessages = (full['messages'] as List? ?? []);
      final newMessages = rawMessages
          .map(
            (m) => ChatMessage(
              id: m['id']?.toString() ?? '',
              text: m['message'] ?? '',
              isSentByUser: m['sender_id']?.toString() == myId?.toString(),
              senderName: m['sender']?['name'] as String?,
              timestamp: m['created_at'] != null
                  ? DateTime.tryParse(m['created_at']) ?? DateTime.now()
                  : DateTime.now(),
            ),
          )
          .toList();

      setState(() {
        _messages = newMessages;
        _loadingMessages = false;
      });
      if (!silent) _scrollToBottom();
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingMessages = false);
    }
  }

  Future<void> _sendMessage() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty || _sending || _activeConversation == null) return;

    HapticFeedback.lightImpact();
    setState(() {
      _sending = true;
      // Optimistic add
      _messages.add(
        ChatMessage(
          id: 'temp_${DateTime.now().millisecondsSinceEpoch}',
          text: text,
          isSentByUser: true,
          timestamp: DateTime.now(),
        ),
      );
    });
    _msgCtrl.clear();
    _scrollToBottom();

    try {
      final api = Provider.of<ApiService>(context, listen: false);
      await api.sendMessage(_activeConversation!['id'].toString(), text);
      if (!mounted) return;
      setState(() => _sending = false);
      _fetchMessages(silent: true);
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _messages.removeLast(); // revert optimistic
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Send failed. Please try again.'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_loadingConvs) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_activeConversation == null) {
      return _buildNoChatState();
    }

    return Column(
      children: [
        // Header
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          color: AppColors.backgroundLight,
          child: Row(
            children: [
              const CircleAvatar(
                backgroundColor: AppColors.primaryGreen,
                radius: 18,
                child: Icon(Icons.support_agent, color: Colors.white, size: 18),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    Text(
                      'Farmmantra Support',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                      ),
                    ),
                    Row(
                      children: [
                        Icon(Icons.circle, size: 8, color: AppColors.success),
                        SizedBox(width: 4),
                        Text(
                          'Online',
                          style: TextStyle(
                            fontSize: 11,
                            color: AppColors.success,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              IconButton(
                icon: const Icon(Icons.refresh, color: AppColors.primaryGreen),
                onPressed: () => _fetchMessages(),
              ),
            ],
          ),
        ),

        // Messages
        Expanded(
          child: _loadingMessages && _messages.isEmpty
              ? const Center(child: CircularProgressIndicator())
              : _messages.isEmpty
              ? const Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.chat_bubble_outline,
                        size: 52,
                        color: AppColors.textLight,
                      ),
                      SizedBox(height: 12),
                      Text(
                        'Start the conversation!',
                        style: TextStyle(color: AppColors.textSecondary),
                      ),
                    ],
                  ),
                )
              : ListView.builder(
                  controller: _scrollCtrl,
                  padding: const EdgeInsets.all(16),
                  itemCount: _messages.length,
                  itemBuilder: (_, i) => _buildBubble(_messages[i]),
                ),
        ),

        // Input bar
        _buildInputBar(),
      ],
    );
  }

  Widget _buildNoChatState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                color: AppColors.primaryGreen.withAlpha(20),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.mark_chat_read_outlined,
                size: 52,
                color: AppColors.primaryGreen,
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Live Support Chat',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            const Text(
              'Chat directly with a Farmmantra support\nagent in real time.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.textSecondary, height: 1.5),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: _startNewChat,
              icon: const Icon(Icons.chat, color: Colors.white),
              label: const Text(
                'Start Live Chat',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primaryGreen,
                padding: const EdgeInsets.symmetric(
                  horizontal: 28,
                  vertical: 14,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBubble(ChatMessage msg) {
    final isMine = msg.isSentByUser;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        mainAxisAlignment: isMine
            ? MainAxisAlignment.end
            : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isMine) ...[
            const CircleAvatar(
              radius: 14,
              backgroundColor: AppColors.primaryGreen,
              child: Icon(Icons.support_agent, color: Colors.white, size: 14),
            ),
            const SizedBox(width: 6),
          ],
          ConstrainedBox(
            constraints: BoxConstraints(
              maxWidth: MediaQuery.of(context).size.width * 0.7,
            ),
            child: Material(
              elevation: 1,
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(18),
                topRight: const Radius.circular(18),
                bottomLeft: Radius.circular(isMine ? 18 : 4),
                bottomRight: Radius.circular(isMine ? 4 : 18),
              ),
              color: isMine ? AppColors.primaryGreen : Colors.white,
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 10,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (!isMine && msg.senderName != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 2),
                        child: Text(
                          msg.senderName!,
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            color: AppColors.primaryGreenDark,
                          ),
                        ),
                      ),
                    Text(
                      msg.text,
                      style: TextStyle(
                        fontSize: 14,
                        color: isMine ? Colors.white : AppColors.textPrimary,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${msg.timestamp.hour.toString().padLeft(2, '0')}:${msg.timestamp.minute.toString().padLeft(2, '0')}',
                      style: TextStyle(
                        fontSize: 10,
                        color: isMine ? Colors.white60 : AppColors.textLight,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          if (isMine) ...[
            const SizedBox(width: 6),
            CircleAvatar(
              radius: 14,
              backgroundColor: AppColors.primaryGreen.withAlpha(30),
              child: const Icon(
                Icons.person,
                color: AppColors.primaryGreen,
                size: 14,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildInputBar() {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(15),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: SafeArea(
        child: Row(
          children: [
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: AppColors.backgroundLight,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: TextField(
                  controller: _msgCtrl,
                  maxLines: null,
                  textInputAction: TextInputAction.send,
                  onSubmitted: (_) => _sendMessage(),
                  decoration: const InputDecoration(
                    hintText: 'Type a message...',
                    hintStyle: TextStyle(color: AppColors.textLight),
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.symmetric(
                      horizontal: 18,
                      vertical: 12,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: _sendMessage,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: _sending
                      ? AppColors.textLight
                      : AppColors.primaryGreen,
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primaryGreen.withAlpha(60),
                      blurRadius: 8,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: _sending
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(
                        Icons.send_rounded,
                        color: Colors.white,
                        size: 18,
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════
// Data model
// ════════════════════════════════════════════════════════════════

class ChatMessage {
  final String id;
  final String text;
  final bool isSentByUser;
  final String? senderName;
  final DateTime timestamp;

  const ChatMessage({
    required this.id,
    required this.text,
    required this.isSentByUser,
    this.senderName,
    required this.timestamp,
  });
}
