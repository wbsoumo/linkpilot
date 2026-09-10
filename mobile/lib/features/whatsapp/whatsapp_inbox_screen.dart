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
  String _activeTab = 'all';

  @override
  void dispose() {
    _searchController.dispose();
    _debounceTimer?.cancel();
    super.dispose();
  }

  void _onSearchChanged(String text) {
    if (_debounceTimer?.isActive ?? false) _debounceTimer!.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 300), () {
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

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            // Top App Bar Header (Logo, Title, Search & Menu)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 12.0),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(
                          color: const Color(0xFF005BF7),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Icon(
                          Icons.send_rounded,
                          size: 20,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 10),
                      const Text(
                        'Link Pilot',
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: Color(0xFF0F172A),
                          letterSpacing: -0.5,
                        ),
                      ),
                    ],
                  ),
                  Row(
                    children: [
                      IconButton(
                        icon: const Icon(Icons.search_rounded, color: Color(0xFF0F172A), size: 24),
                        onPressed: () {},
                      ),
                      IconButton(
                        icon: const Icon(Icons.more_vert_rounded, color: Color(0xFF0F172A), size: 24),
                        onPressed: () {},
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // Page Header Title & Subtitle
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20.0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    Text(
                      'WhatsApp',
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0F172A),
                        letterSpacing: -0.5,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Manage your conversations',
                      style: TextStyle(
                        fontSize: 14,
                        color: Color(0xFF64748B),
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Filter Pills Row (All 12, Unread 5, Starred, Archived)
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 20.0),
              child: Row(
                children: [
                  _buildFilterPill('All', '12', _activeTab == 'all', () {
                    setState(() => _activeTab = 'all');
                  }),
                  const SizedBox(width: 10),
                  _buildFilterPill('Unread', '5', _activeTab == 'unread', () {
                    setState(() => _activeTab = 'unread');
                  }),
                  const SizedBox(width: 10),
                  _buildFilterPillIcon('Starred', Icons.star_outline_rounded, _activeTab == 'starred', () {
                    setState(() => _activeTab = 'starred');
                  }),
                  const SizedBox(width: 10),
                  _buildFilterPillIcon('Archived', Icons.archive_outlined, _activeTab == 'archived', () {
                    setState(() => _activeTab = 'archived');
                  }),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Search Bar & Filter Toggle Button
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20.0),
              child: Row(
                children: [
                  Expanded(
                    child: Container(
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: TextField(
                        controller: _searchController,
                        onChanged: _onSearchChanged,
                        style: const TextStyle(fontSize: 14, color: Color(0xFF0F172A)),
                        decoration: const InputDecoration(
                          hintText: 'Search conversations...',
                          hintStyle: TextStyle(fontSize: 14, color: Color(0xFF94A3B8)),
                          prefixIcon: Icon(Icons.search_rounded, size: 20, color: Color(0xFF94A3B8)),
                          border: InputBorder.none,
                          contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(Icons.tune_rounded, size: 20, color: Color(0xFF475569)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Conversation List
            Expanded(
              child: whatsappAsync.when(
                data: (data) {
                  final conversations = (data['conversations'] as List<dynamic>?) ?? [];
                  
                  if (conversations.isEmpty && _searchQuery.isNotEmpty) {
                    return const Center(
                      child: Text(
                        'No conversations found matching search',
                        style: TextStyle(color: Color(0xFF64748B), fontSize: 14),
                      ),
                    );
                  }

                  // Use real conversations if returned by backend API, otherwise use rich mockup list
                  final displayList = conversations.isNotEmpty 
                      ? conversations 
                      : _getMockChatsList();

                  return RefreshIndicator(
                    onRefresh: () async {
                      ref.invalidate(mobileWhatsAppProvider(_searchQuery));
                    },
                    child: ListView.separated(
                      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
                      itemCount: displayList.length,
                      separatorBuilder: (context, index) => const Divider(height: 1, color: Color(0xFFF1F5F9)),
                      itemBuilder: (context, index) {
                        final item = displayList[index] as Map<String, dynamic>;
                        return _buildMockChatTile(context, item, index);
                      },
                    ),
                  );
                },
                loading: () => const Center(child: CircularProgressIndicator(color: Color(0xFF005BF7))),
                error: (err, _) => Center(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Text(
                      'Connection error: $err',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Color(0xFFEF4444), fontSize: 13),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {},
        backgroundColor: const Color(0xFF005BF7),
        elevation: 4,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: const Icon(Icons.chat_bubble_outline_rounded, color: Colors.white, size: 24),
      ),
    );
  }

  Widget _buildFilterPill(String title, String badgeCount, bool isSelected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFEFF6FF) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? const Color(0xFFBFDBFE) : const Color(0xFFE2E8F0),
            width: 1,
          ),
        ),
        child: Row(
          children: [
            Text(
              title,
              style: TextStyle(
                fontSize: 14,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.w600,
                color: isSelected ? const Color(0xFF005BF7) : const Color(0xFF64748B),
              ),
            ),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: isSelected ? const Color(0xFF005BF7) : const Color(0xFFCBD5E1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                badgeCount,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFilterPillIcon(String title, IconData icon, bool isSelected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFFEFF6FF) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isSelected ? const Color(0xFFBFDBFE) : const Color(0xFFE2E8F0),
            width: 1,
          ),
        ),
        child: Row(
          children: [
            Icon(icon, size: 16, color: isSelected ? const Color(0xFF005BF7) : const Color(0xFF64748B)),
            const SizedBox(width: 6),
            Text(
              title,
              style: TextStyle(
                fontSize: 14,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.w600,
                color: isSelected ? const Color(0xFF005BF7) : const Color(0xFF64748B),
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Map<String, dynamic>> _getMockChatsList() {
    return [
      {
        'id': 1,
        'name': 'Rahul Mehta',
        'last_message': 'Can you share the latest report?',
        'last_message_time': '11:02 AM',
        'unread_count': 2,
        'has_online': true,
      },
      {
        'id': 2,
        'name': 'ABC Coaching',
        'last_message': 'We need the final files by today.',
        'last_message_time': '9:40 AM',
        'unread_count': 1,
        'initials_bg': const Color(0xFF2DD4BF),
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
        'initials_bg': const Color(0xFFF472B6),
      },
      {
        'id': 6,
        'name': 'Pathfinder Institute',
        'last_message': 'Admissions are now open for 2025.',
        'last_message_time': 'Mon',
        'unread_count': 0,
        'is_muted': true,
      },
      {
        'id': 7,
        'name': 'Client Support',
        'last_message': 'Your request has been received.',
        'last_message_time': 'Sep 8',
        'unread_count': 3,
      },
      {
        'id': 8,
        'name': 'Team Updates',
        'last_message': 'Rohit: Great work!',
        'last_message_time': 'Sep 7',
        'unread_count': 0,
        'initials_bg': const Color(0xFF86EFAC),
      },
      {
        'id': 9,
        'name': 'Sneha Iyer',
        'last_message': '📷 Photo',
        'last_message_time': 'Sep 6',
        'unread_count': 0,
      },
    ];
  }

  Widget _buildMockConversationList(BuildContext context) {
    final mockChats = _getMockChatsList();

    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
      itemCount: mockChats.length,
      separatorBuilder: (context, index) => const Divider(height: 1, color: Color(0xFFF1F5F9)),
      itemBuilder: (context, index) {
        return _buildMockChatTile(context, mockChats[index], index);
      },
    );
  }

  Widget _buildMockChatTile(BuildContext context, Map<String, dynamic> item, int index) {
    final name = item['name'] ?? 'Contact';
    final msg = item['last_message'] ?? '';
    final time = item['last_message_time'] ?? '';
    final unread = (item['unread_count'] ?? 0) as int;
    final hasOnline = item['has_online'] == true;
    final isMuted = item['is_muted'] == true;
    final initialsBg = item['initials_bg'] as Color? ?? const Color(0xFF38BDF8);

    return InkWell(
      onTap: () => _openChatScreen(context, item),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12.0),
        child: Row(
          children: [
            // Avatar Stack with Online Badge
            Stack(
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundColor: initialsBg.withValues(alpha: 0.2),
                  child: Text(
                    name.isNotEmpty ? name[0].toUpperCase() : 'C',
                    style: TextStyle(
                      color: initialsBg,
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                    ),
                  ),
                ),
                if (hasOnline)
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: Container(
                      width: 13,
                      height: 13,
                      decoration: BoxDecoration(
                        color: const Color(0xFF22C55E),
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 2),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    msg,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 13,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  time,
                  style: const TextStyle(
                    fontSize: 12,
                    color: Color(0xFF94A3B8),
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 6),
                if (unread > 0)
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: const BoxDecoration(
                      color: Color(0xFF005BF7),
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      '$unread',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  )
                else if (isMuted)
                  const Icon(Icons.notifications_off_outlined, size: 16, color: Color(0xFF94A3B8)),
              ],
            ),
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
