import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/providers/providers.dart';
import '../../core/theme/theme.dart';

class WhatsAppInboxScreen extends ConsumerStatefulWidget {
  const WhatsAppInboxScreen({super.key});

  @override
  ConsumerState<WhatsAppInboxScreen> createState() => _WhatsAppInboxScreenState();
}

class _WhatsAppInboxScreenState extends ConsumerState<WhatsAppInboxScreen> {
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounceTimer;
  String _searchQuery = '';

  @override
  void dispose() {
    _searchController.dispose();
    _debounceTimer?.cancel();
    super.dispose();
  }

  void _onSearchChanged(String text) {
    if (_debounceTimer?.isActive ?? false) _debounceTimer!.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 400), () {
      setState(() {
        _searchQuery = text.trim();
      });
    });
  }

  void _openChatScreen(BuildContext context, Map<String, dynamic> conversation) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => WhatsAppChatDetailScreen(conversation: conversation),
      ),
    ).then((_) => ref.invalidate(mobileWhatsAppProvider(_searchQuery)));
  }

  @override
  Widget build(BuildContext context) {
    final whatsappAsync = ref.watch(mobileWhatsAppProvider(_searchQuery));
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.bgSlate,
      appBar: AppBar(
        title: const Text('WhatsApp', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 24)),
        actions: [
          IconButton(
            icon: const Icon(Icons.search_rounded, size: 24),
            onPressed: () {},
          ),
        ],
      ),
      body: Column(
        children: [
          // Search Input (Mockup 8)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Search conversations',
                hintStyle: const TextStyle(fontSize: 14, color: AppTheme.textSecondaryLight),
                prefixIcon: const Icon(Icons.search_rounded, size: 20, color: AppTheme.textSecondaryLight),
                filled: true,
                fillColor: isDark ? AppTheme.slateCard : const Color(0xFFF1F5F9),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),

          // Conversation List
          Expanded(
            child: whatsappAsync.when(
              data: (data) {
                final conversations = (data['conversations'] as List<dynamic>?) ?? [];
                if (conversations.isEmpty) {
                  return _buildMockConversationList(context);
                }

                return RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(mobileWhatsAppProvider(_searchQuery));
                  },
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
                    itemCount: conversations.length,
                    itemBuilder: (context, index) {
                      final item = conversations[index] as Map<String, dynamic>;
                      return _buildChatTile(context, item);
                    },
                  ),
                );
              },
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (_, __) => _buildMockConversationList(context),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {},
        backgroundColor: AppTheme.greenWhatsApp,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: const Icon(Icons.chat_rounded, color: Colors.white),
      ),
    );
  }

  Widget _buildMockConversationList(BuildContext context) {
    final mockChats = [
      {
        'id': 1,
        'name': 'Rahul Mehta',
        'last_message': 'Can you share the latest report?',
        'last_message_time': '11:02 AM',
        'unread_count': 2,
      },
      {
        'id': 2,
        'name': 'ABC Coaching',
        'last_message': 'We need the final files by today.',
        'last_message_time': '9:40 AM',
        'unread_count': 1,
      },
      {
        'id': 3,
        'name': 'Priya Sharma',
        'last_message': 'Thanks! I\'ll check and get back.',
        'last_message_time': 'Yesterday',
        'unread_count': 0,
      },
      {
        'id': 4,
        'name': 'Karan Verma',
        'last_message': 'Sure, I\'ll send it by EOD.',
        'last_message_time': 'Yesterday',
        'unread_count': 0,
      },
      {
        'id': 5,
        'name': 'Neha Gupta',
        'last_message': 'Let\'s connect tomorrow.',
        'last_message_time': 'Mon',
        'unread_count': 0,
      },
      {
        'id': 6,
        'name': 'Client Support',
        'last_message': 'Your request has been received.',
        'last_message_time': 'Mon',
        'unread_count': 0,
      },
      {
        'id': 7,
        'name': 'Team Updates',
        'last_message': 'Rohit: Great work!',
        'last_message_time': 'Sun',
        'unread_count': 0,
      },
    ];

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
      itemCount: mockChats.length,
      itemBuilder: (context, index) {
        return _buildChatTile(context, mockChats[index]);
      },
    );
  }

  Widget _buildChatTile(BuildContext context, Map<String, dynamic> item) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final name = item['name'] ?? 'Contact';
    final msg = item['last_message'] ?? '';
    final time = item['last_message_time'] ?? '';
    final unread = (item['unread_count'] ?? 0) as int;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: InkWell(
        onTap: () => _openChatScreen(context, item),
        child: Row(
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor: AppTheme.brandBlue.withValues(alpha: 0.15),
              child: Text(
                name[0].toUpperCase(),
                style: const TextStyle(
                  color: AppTheme.brandBlue,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        name,
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                        ),
                      ),
                      Text(
                        time,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppTheme.textSecondaryLight,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(
                    msg,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppTheme.textSecondaryLight,
                    ),
                  ),
                ],
              ),
            ),
            if (unread > 0) ...[
              const SizedBox(width: 8),
              CircleAvatar(
                radius: 10,
                backgroundColor: AppTheme.brandBlue,
                child: Text(
                  '$unread',
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class WhatsAppChatDetailScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> conversation;
  const WhatsAppChatDetailScreen({super.key, required this.conversation});

  @override
  ConsumerState<WhatsAppChatDetailScreen> createState() => _WhatsAppChatDetailScreenState();
}

class _WhatsAppChatDetailScreenState extends ConsumerState<WhatsAppChatDetailScreen> {
  final TextEditingController _msgController = TextEditingController();

  final List<Map<String, dynamic>> _mockMessages = [
    {
      'sender': 'them',
      'text': 'Hi, can you share the latest report?',
      'time': '10:58 AM',
    },
    {
      'sender': 'me',
      'text': 'Sure, I\'ll send it shortly.',
      'time': '10:59 AM',
    },
    {
      'sender': 'them',
      'text': 'Also, let me know if you need any changes.',
      'time': '11:00 AM',
    },
    {
      'sender': 'me',
      'text': 'That would be great. Thanks!',
      'time': '11:01 AM',
    },
  ];

  @override
  void dispose() {
    _msgController.dispose();
    super.dispose();
  }

  void _sendMessage() {
    final text = _msgController.text.trim();
    if (text.isEmpty) return;

    setState(() {
      _mockMessages.add({
        'sender': 'me',
        'text': text,
        'time': 'Just now',
      });
      _msgController.clear();
    });
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final name = widget.conversation['name'] ?? 'Rahul Mehta';

    return Scaffold(
      backgroundColor: isDark ? AppTheme.obsidianBlack : const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: isDark ? AppTheme.slateCard : Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          onPressed: () => Navigator.pop(context),
        ),
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundColor: AppTheme.brandBlue.withValues(alpha: 0.15),
              child: Text(name[0].toUpperCase(), style: const TextStyle(color: AppTheme.brandBlue, fontWeight: FontWeight.bold)),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                const Text('online', style: TextStyle(fontSize: 12, color: AppTheme.greenWhatsApp, fontWeight: FontWeight.w500)),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(icon: const Icon(Icons.videocam_outlined, size: 22), onPressed: () {}),
          IconButton(icon: const Icon(Icons.call_outlined, size: 20), onPressed: () {}),
        ],
      ),
      body: Column(
        children: [
          // Chat Message History (Mockup 9)
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _mockMessages.length,
              itemBuilder: (context, index) {
                final msg = _mockMessages[index];
                final isMe = msg['sender'] == 'me';

                return Align(
                  alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
                    decoration: BoxDecoration(
                      color: isMe
                          ? const Color(0xFFDCF8C6) // WhatsApp Green message bubble
                          : (isDark ? AppTheme.slateCard : Colors.white),
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.03),
                          blurRadius: 4,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          msg['text']!,
                          style: const TextStyle(
                            fontSize: 14,
                            color: Colors.black87,
                            height: 1.3,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              msg['time']!,
                              style: const TextStyle(fontSize: 10, color: Colors.grey),
                            ),
                            if (isMe) ...[
                              const SizedBox(width: 4),
                              const Icon(Icons.done_all_rounded, size: 14, color: AppTheme.brandBlue),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),

          // Bottom Input Bar (Mockup 9)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.slateCard : Colors.white,
              border: Border(top: BorderSide(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate)),
            ),
            child: SafeArea(
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _msgController,
                      decoration: InputDecoration(
                        hintText: 'Type a message...',
                        hintStyle: const TextStyle(fontSize: 14, color: AppTheme.textSecondaryLight),
                        prefixIcon: const Icon(Icons.search_rounded, size: 20, color: Colors.transparent),
                        suffixIcon: IconButton(
                          icon: const Icon(Icons.attach_file_rounded, size: 20, color: AppTheme.textSecondaryLight),
                          onPressed: () {},
                        ),
                        filled: true,
                        fillColor: isDark ? AppTheme.obsidianBlack : const Color(0xFFF1F5F9),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(24),
                          borderSide: BorderSide.none,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  CircleAvatar(
                    backgroundColor: AppTheme.brandBlue,
                    child: IconButton(
                      icon: const Icon(Icons.send_rounded, color: Colors.white, size: 18),
                      onPressed: _sendMessage,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
