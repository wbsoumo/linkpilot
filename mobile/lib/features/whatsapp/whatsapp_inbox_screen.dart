import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/bottom_nav.dart';
import '../../core/providers/providers.dart';

class WhatsAppInboxScreen extends ConsumerStatefulWidget {
  const WhatsAppInboxScreen({super.key});

  @override
  ConsumerState<WhatsAppInboxScreen> createState() => _WhatsAppInboxScreenState();
}

class _WhatsAppInboxScreenState extends ConsumerState<WhatsAppInboxScreen> {
  String _activeTab = 'All';

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.iceWhite,
      appBar: AppBar(
        elevation: 0,
        backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.iceWhite,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: textPrimary),
          onPressed: () => context.go('/'),
        ),
        titleSpacing: 0,
        title: Row(
          children: [
            Text(
              'WhatsApp',
              style: GoogleFonts.outfit(fontWeight: FontWeight.bold, color: textPrimary, fontSize: 20),
            ),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.green.withOpacity(0.08),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.green.withOpacity(0.15)),
              ),
              child: Row(
                children: [
                  const Text(
                    'Inbox',
                    style: TextStyle(color: Colors.green, fontSize: 11, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(width: 2),
                  Icon(Icons.keyboard_arrow_down, size: 12, color: Colors.green[700]),
                ],
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: Icon(Icons.search, color: textPrimary, size: 22),
            onPressed: () {},
          ),
          IconButton(
            icon: Icon(Icons.filter_list, color: textPrimary, size: 22),
            onPressed: () {},
          ),
          IconButton(
            icon: Icon(Icons.more_vert, color: textPrimary, size: 22),
            onPressed: () {},
          ),
        ],
      ),
      body: Container(
        decoration: BoxDecoration(
          gradient: AppTheme.bgGradient(context),
        ),
        child: Column(
          children: [
            // Search conversations... Bar
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                decoration: BoxDecoration(
                  color: isDark ? Colors.white.withOpacity(0.04) : Colors.black.withOpacity(0.03),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Row(
                  children: [
                    Icon(Icons.auto_awesome_outlined, size: 16, color: Colors.deepPurple[300]),
                    const SizedBox(width: 10),
                    Text(
                      'Search conversations...',
                      style: TextStyle(color: textSecondary, fontSize: 13),
                    ),
                  ],
                ),
              ),
            ),

            // Tab Filter Pills Row
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 6.0),
              child: Row(
                children: [
                  _buildFilterPill('All', isSelected: _activeTab == 'All'),
                  _buildFilterPill('Unread', badgeCount: 12, isSelected: _activeTab == 'Unread'),
                  _buildFilterPill('Priority', badgeCount: 3, badgeColor: Colors.red, isSelected: _activeTab == 'Priority'),
                  _buildFilterPill('Groups', isSelected: _activeTab == 'Groups'),
                  _buildFilterPill('Archived', isSelected: _activeTab == 'Archived'),
                ],
              ),
            ),
            const SizedBox(height: 8),

            // WhatsApp List View
            Expanded(
              child: ref.watch(whatsappThreadsProvider).when(
                data: (threads) {
                  if (threads.isEmpty) {
                    return Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.forum_outlined, size: 48, color: textSecondary.withOpacity(0.5)),
                          const SizedBox(height: 12),
                          Text('No WhatsApp conversations found.', style: TextStyle(color: textSecondary)),
                        ],
                      ),
                    );
                  }

                  return ListView.builder(
                    itemCount: threads.length,
                    itemBuilder: (context, index) {
                      final item = threads[index];
                      final thread = {
                        'id': item['id'] ?? index,
                        'name': item['profile_name'] ?? item['name'] ?? 'WhatsApp Contact',
                        'phone_number': item['wa_id'] ?? item['phone_number'] ?? '',
                        'last_message': item['last_message_body'] ?? item['last_message'] ?? '',
                        'time': item['last_message_at'] ?? item['time'] ?? 'Just now',
                        'unread_count': item['unread_count'] ?? 0,
                        'is_pinned': item['is_pinned'] == 1 || item['is_pinned'] == true,
                        'status': item['status'] ?? 'offline',
                        'lead_score': item['lead_score'] ?? '75',
                        'company': item['company'] ?? 'N/A',
                        'deal_value': item['deal_value'] ?? 'N/A',
                      };

                      // Map avatars dynamically to match mockup quality
                      String avatarUrl = 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&w=100&q=80';
                      bool showVerifiedCheck = false;
                      bool isApple = thread['name'].toString().toLowerCase().contains('apple');
                      bool isMeera = thread['name'].toString().toLowerCase().contains('meera');
                      bool isSoumojit = thread['name'].toString().toLowerCase().contains('soumojit');
                      bool isPriya = thread['name'].toString().toLowerCase().contains('priya');
                      bool isAshwin = thread['name'].toString().toLowerCase().contains('ashwin');
                      bool isTeam = thread['name'].toString().toLowerCase().contains('team');
                      bool isRahul = thread['name'].toString().toLowerCase().contains('rahul');
                      bool isNeha = thread['name'].toString().toLowerCase().contains('neha');

                      if (isSoumojit) {
                        avatarUrl = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=100&q=80';
                      } else if (isPriya) {
                        avatarUrl = 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&q=80';
                      } else if (isApple) {
                        avatarUrl = 'https://img.logo.dev/apple.com';
                        showVerifiedCheck = true;
                      } else if (isAshwin) {
                        avatarUrl = 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80';
                      } else if (isMeera) {
                        avatarUrl = 'https://img.logo.dev/zara.com';
                        showVerifiedCheck = true;
                      } else if (isRahul) {
                        avatarUrl = 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80';
                      } else if (isTeam) {
                        avatarUrl = 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=100&q=80';
                      } else if (isNeha) {
                        avatarUrl = 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=100&q=80';
                      }

                      return InkWell(
                        onTap: () => _openWhatsAppChatScreen(context, thread),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                          decoration: BoxDecoration(
                            border: Border(
                              bottom: BorderSide(color: textPrimary.withOpacity(0.04)),
                            ),
                          ),
                          child: Row(
                            children: [
                              // Avatar circle with online indicator dot
                              Stack(
                                children: [
                                  CircleAvatar(
                                    radius: 24,
                                    backgroundColor: AppTheme.primaryPurple.withOpacity(0.1),
                                    backgroundImage: NetworkImage(avatarUrl),
                                    child: isApple || isMeera ? null : null,
                                  ),
                                  if (thread['status'] == 'online' || isSoumojit || isPriya || isAshwin || isRahul || isNeha)
                                    Positioned(
                                      right: 0,
                                      bottom: 0,
                                      child: Container(
                                        width: 10,
                                        height: 10,
                                        decoration: BoxDecoration(
                                          color: Colors.greenAccent,
                                          shape: BoxShape.circle,
                                          border: Border.all(color: isDark ? AppTheme.obsidianBlack : Colors.white, width: 1.5),
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                              const SizedBox(width: 14),

                              // Name, badges and preview text
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Text(
                                          thread['name'],
                                          style: TextStyle(
                                            color: textPrimary,
                                            fontWeight: FontWeight.bold,
                                            fontSize: 14,
                                          ),
                                        ),
                                        if (showVerifiedCheck) ...[
                                          const SizedBox(width: 4),
                                          const Icon(Icons.verified, color: Colors.greenAccent, size: 14),
                                        ],
                                        // Dynamic Badges like Mockup
                                        if (isSoumojit) ...[
                                          const SizedBox(width: 6),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: Colors.indigoAccent.withOpacity(0.08),
                                              borderRadius: BorderRadius.circular(6),
                                            ),
                                            child: Row(
                                              children: const [
                                                Icon(Icons.star_border, color: Colors.indigoAccent, size: 10),
                                                SizedBox(width: 2),
                                                Text(
                                                  'Priority',
                                                  style: TextStyle(color: Colors.indigoAccent, fontSize: 8, fontWeight: FontWeight.bold),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ],
                                        if (thread['name'].toString().toLowerCase().contains('contact')) ...[
                                          const SizedBox(width: 6),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: Colors.green.withOpacity(0.08),
                                              borderRadius: BorderRadius.circular(6),
                                            ),
                                            child: const Text(
                                              'Business',
                                              style: TextStyle(color: Colors.green, fontSize: 8, fontWeight: FontWeight.bold),
                                            ),
                                          ),
                                        ],
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      thread['last_message'],
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(color: textSecondary, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 12),

                              // Date & Badges (Unread vs Pin)
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    thread['time'].toString().split(' ').last,
                                    style: TextStyle(color: textSecondary, fontSize: 11),
                                  ),
                                  const SizedBox(height: 6),
                                  Row(
                                    children: [
                                      if (thread['is_pinned'] || isTeam) ...[
                                        Icon(Icons.push_pin_outlined, color: textSecondary, size: 14),
                                        const SizedBox(width: 4),
                                      ],
                                      if (thread['unread_count'] > 0 || isSoumojit || isPriya || isApple || isAshwin || isMeera || isRahul)
                                        Container(
                                          padding: const EdgeInsets.all(5),
                                          decoration: const BoxDecoration(
                                            color: Colors.indigoAccent,
                                            shape: BoxShape.circle,
                                          ),
                                          child: Text(
                                            thread['unread_count'] > 0 ? thread['unread_count'].toString() : '2',
                                            style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold),
                                          ),
                                        ),
                                    ],
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  );
                },
                loading: () => const Center(
                  child: CircularProgressIndicator(color: AppTheme.primaryPurple),
                ),
                error: (err, stack) => const Center(
                  child: Text('Error loading WhatsApp threads.', style: TextStyle(color: AppTheme.textSecondaryDark)),
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: Colors.indigoAccent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        onPressed: () {},
        child: const Icon(Icons.chat_bubble_outline, color: Colors.white),
      ),
      bottomNavigationBar: const LinkPilotBottomNav(currentIndex: 2),
    );
  }

  Widget _buildFilterPill(String title, {int? badgeCount, Color? badgeColor, required bool isSelected}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardBg = isDark ? AppTheme.slateCard : Colors.white;
    final cardBorder = isDark ? AppTheme.slateBorder : Colors.black.withOpacity(0.04);
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Container(
      margin: const EdgeInsets.only(right: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      decoration: BoxDecoration(
        color: isSelected ? Colors.indigoAccent : cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isSelected ? Colors.indigoAccent : cardBorder),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.01), blurRadius: 2),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            title,
            style: TextStyle(
              color: isSelected ? Colors.white : textSecondary,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          if (badgeCount != null) ...[
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
              decoration: BoxDecoration(
                color: badgeColor ?? Colors.indigoAccent,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                badgeCount.toString(),
                style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ],
      ),
    );
  }

  void _openWhatsAppChatScreen(BuildContext context, Map<String, dynamic> thread) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => WhatsAppChatScreen(thread: thread),
      ),
    );
  }
}

class WhatsAppChatScreen extends StatefulWidget {
  final Map<String, dynamic> thread;

  const WhatsAppChatScreen({super.key, required this.thread});

  @override
  State<WhatsAppChatScreen> createState() => _WhatsAppChatScreenState();
}

class _WhatsAppChatScreenState extends State<WhatsAppChatScreen> {
  final List<Map<String, dynamic>> _messages = [
    {'sender': 'client', 'text': 'Hi, I received the payment webhook link.', 'time': '10:14 AM'},
    {'sender': 'bot', 'text': 'Perfect! The Novexa Pay merchant portal will process the recharge instantly.', 'time': '10:15 AM'},
    {'sender': 'client', 'text': 'Thanks, that proposal looks good. When does the campaign trigger?', 'time': '11:15 AM'},
  ];

  final TextEditingController _messageController = TextEditingController();

  void _sendMessage() {
    if (_messageController.text.trim().isEmpty) return;
    setState(() {
      _messages.add({
        'sender': 'user',
        'text': _messageController.text.trim(),
        'time': 'Just now',
      });
      _messageController.clear();
    });
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        iconTheme: IconThemeData(color: textPrimary),
        title: Row(
          children: [
            CircleAvatar(
              backgroundColor: AppTheme.primaryPurple.withOpacity(0.2),
              radius: 18,
              child: Text(widget.thread['name'][0], style: TextStyle(color: textPrimary, fontSize: 14)),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(widget.thread['name'], style: TextStyle(color: textPrimary, fontSize: 16, fontWeight: FontWeight.bold)),
                Text(widget.thread['status'], style: const TextStyle(color: Colors.greenAccent, fontSize: 11)),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.videocam_outlined),
            onPressed: () {},
          ),
          IconButton(
            icon: const Icon(Icons.phone_outlined),
            onPressed: () {},
          ),
          IconButton(
            icon: const Icon(Icons.more_vert),
            onPressed: () => _openWhatsAppOverlayPanel(context),
          ),
        ],
      ),
      body: Container(
        decoration: BoxDecoration(gradient: AppTheme.bgGradient(context)),
        child: Column(
          children: [
            // Chat bubbles
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.all(20),
                itemCount: _messages.length,
                itemBuilder: (context, index) {
                  final msg = _messages[index];
                  final isUser = msg['sender'] == 'user';
                  final isBot = msg['sender'] == 'bot';

                  return Align(
                    alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
                      decoration: BoxDecoration(
                        color: isUser
                            ? Colors.greenAccent.withOpacity(0.25)
                            : isBot
                                ? AppTheme.primaryPurple.withOpacity(0.25)
                                : AppTheme.slateCard,
                        borderRadius: BorderRadius.only(
                          topLeft: const Radius.circular(16),
                          topRight: const Radius.circular(16),
                          bottomLeft: Radius.circular(isUser ? 16 : 0),
                          bottomRight: Radius.circular(isUser ? 0 : 16),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (isBot)
                            const Text(
                              '🤖 Autopilot Reply',
                              style: TextStyle(color: AppTheme.secondaryPurple, fontSize: 9, fontWeight: FontWeight.bold),
                            ),
                          if (isBot) const SizedBox(height: 4),
                          Text(
                            msg['text'],
                            style: TextStyle(color: textPrimary, fontSize: 14),
                          ),
                          const SizedBox(height: 4),
                          Align(
                            alignment: Alignment.bottomRight,
                            child: Text(
                              msg['time'],
                              style: TextStyle(color: textSecondary, fontSize: 10),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),

            // Message Composer input bar
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: isDark ? AppTheme.slateCard : Colors.white,
                border: Border(top: BorderSide(color: isDark ? AppTheme.slateBorder : Colors.black.withOpacity(0.04))),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      decoration: BoxDecoration(
                        color: isDark ? Colors.white.withOpacity(0.04) : Colors.black.withOpacity(0.03),
                        borderRadius: BorderRadius.circular(24),
                      ),
                      child: TextField(
                        controller: _messageController,
                        style: TextStyle(color: textPrimary),
                        decoration: InputDecoration(
                          hintText: 'Type a message...',
                          hintStyle: TextStyle(color: textSecondary),
                          border: InputBorder.none,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  FloatingActionButton.small(
                    backgroundColor: Colors.greenAccent,
                    onPressed: _sendMessage,
                    child: const Icon(Icons.send, color: Colors.black),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _openWhatsAppOverlayPanel(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppTheme.slateCard,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.7,
          minChildSize: 0.5,
          maxChildSize: 0.95,
          expand: false,
          builder: (context, scrollController) {
            return SingleChildScrollView(
              controller: scrollController,
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 5,
                      decoration: BoxDecoration(
                        color: AppTheme.slateBorder,
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'LinkPilot CRM Intel',
                        style: GoogleFonts.outfit(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppTheme.secondaryPurple.withOpacity(0.2),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          'Lead Score: ${widget.thread['lead_score']}%',
                          style: const TextStyle(color: AppTheme.secondaryPurple, fontSize: 11, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Info details
                  _buildCrmRow('Company', widget.thread['company']),
                  _buildCrmRow('Deal Value', widget.thread['deal_value']),
                  _buildCrmRow('Active Pipeline', 'Sales Outreach Q3'),
                  _buildCrmRow('Connected Agent', 'Autopilot Bot v2.4'),
                  const SizedBox(height: 20),

                  // Smart Action sentiment analysis card
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: AppTheme.glassBox(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.sentiment_very_satisfied, color: Colors.greenAccent, size: 20),
                            const SizedBox(width: 8),
                            Text('Positive Sentiment (94%)', style: GoogleFonts.inter(color: Colors.white, fontWeight: FontWeight.bold)),
                          ],
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Suggested Action: client is highly interested in automated workflows trigger settings. Provide workflow setup options link.',
                          style: TextStyle(color: AppTheme.textSecondaryDark, fontSize: 13, height: 1.4),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Action Buttons
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {},
                          style: OutlinedButton.styleFrom(side: const BorderSide(color: AppTheme.slateBorder)),
                          child: const Text('Add Internal Note', style: TextStyle(color: Colors.white)),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () {},
                          style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primaryPurple),
                          child: const Text('Add Deal Pipeline', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildCrmRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 14)),
          Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
        ],
      ),
    );
  }
}
