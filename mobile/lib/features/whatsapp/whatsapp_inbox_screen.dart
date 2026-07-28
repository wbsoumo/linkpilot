import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/bottom_nav.dart';
import '../../core/widgets/ai_floating_button.dart';
import '../../core/providers/providers.dart';

class WhatsAppInboxScreen extends ConsumerStatefulWidget {
  const WhatsAppInboxScreen({super.key});

  @override
  ConsumerState<WhatsAppInboxScreen> createState() => _WhatsAppInboxScreenState();
}

class _WhatsAppInboxScreenState extends ConsumerState<WhatsAppInboxScreen> {


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'WhatsApp Inbox',
          style: GoogleFonts.outfit(fontWeight: FontWeight.bold, color: Colors.white),
        ),
        backgroundColor: AppTheme.obsidianBlack,
        actions: [
          IconButton(
            icon: const Icon(Icons.search, color: Colors.white),
            onPressed: () {},
          ),
          IconButton(
            icon: const Icon(Icons.filter_list, color: Colors.white),
            onPressed: () {},
          ),
        ],
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: AppTheme.premiumDarkGradient,
        ),
        child: ref.watch(whatsappThreadsProvider).when(
          data: (threads) {
            if (threads.isEmpty) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.forum_outlined, size: 48, color: AppTheme.textSecondaryDark.withOpacity(0.5)),
                    const SizedBox(height: 12),
                    const Text('No WhatsApp conversations found.', style: TextStyle(color: AppTheme.textSecondaryDark)),
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
                return InkWell(
                  onTap: () => _openWhatsAppChatScreen(context, thread),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                    decoration: BoxDecoration(
                      border: Border(
                        bottom: BorderSide(color: Colors.white.withOpacity(0.05)),
                      ),
                    ),
                    child: Row(
                      children: [
                        // Avatar with online indicator
                        Stack(
                          children: [
                            CircleAvatar(
                              backgroundColor: AppTheme.primaryPurple.withOpacity(0.2),
                              radius: 26,
                              child: Text(
                                thread['name'][0],
                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
                              ),
                            ),
                            if (thread['status'] == 'online')
                              Positioned(
                                right: 2,
                                bottom: 2,
                                child: Container(
                                  width: 12,
                                  height: 12,
                                  decoration: BoxDecoration(
                                    color: Colors.greenAccent,
                                    shape: BoxShape.circle,
                                    border: Border.all(color: AppTheme.obsidianBlack, width: 2),
                                  ),
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(width: 16),

                        // Title & preview
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    thread['name'],
                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                                  ),
                                  Text(
                                    thread['time'].toString().split(' ').last,
                                    style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                thread['last_message'],
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 13),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),

                        // Badges (Pins & Unreads)
                        Column(
                          children: [
                            if (thread['is_pinned'])
                              const Icon(Icons.push_pin, color: AppTheme.textSecondaryDark, size: 16),
                            const SizedBox(height: 6),
                            if (thread['unread_count'] > 0)
                              Container(
                                padding: const EdgeInsets.all(6),
                                decoration: const BoxDecoration(
                                  color: AppTheme.primaryPurple,
                                  shape: BoxShape.circle,
                                ),
                                child: Text(
                                  thread['unread_count'].toString(),
                                  style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                                ),
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
      floatingActionButton: const AiFloatingActionButton(),
      bottomNavigationBar: const LinkPilotBottomNav(currentIndex: 2),
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
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: AppTheme.obsidianBlack,
        title: Row(
          children: [
            CircleAvatar(
              backgroundColor: AppTheme.primaryPurple.withOpacity(0.2),
              radius: 18,
              child: Text(widget.thread['name'][0], style: const TextStyle(color: Colors.white, fontSize: 14)),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(widget.thread['name'], style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
                Text(widget.thread['status'], style: const TextStyle(color: Colors.greenAccent, fontSize: 11)),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.auto_awesome, color: Colors.orangeAccent),
            onPressed: () => _openCRMSidebar(context),
          ),
          IconButton(
            icon: const Icon(Icons.info_outline, color: Colors.white),
            onPressed: () => _openCRMSidebar(context),
          ),
        ],
      ),
      body: Container(
        decoration: const BoxDecoration(
          image: DecorationImage(
            image: NetworkImage('https://i.pinimg.com/736x/8c/8f/c9/8c8fc99a5e1e405f6e814a046c4f0393.jpg'),
            fit: BoxFit.cover,
            opacity: 0.06,
          ),
          color: AppTheme.obsidianBlack,
        ),
        child: Column(
          children: [
            // Chat messages
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _messages.length,
                itemBuilder: (context, index) {
                  final msg = _messages[index];
                  final isMe = msg['sender'] == 'user' || msg['sender'] == 'bot';
                  return Align(
                    alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(
                        color: isMe ? AppTheme.primaryPurple : AppTheme.slateCard,
                        borderRadius: BorderRadius.only(
                          topLeft: const Radius.circular(16),
                          topRight: const Radius.circular(16),
                          bottomLeft: isMe ? const Radius.circular(16) : Radius.zero,
                          bottomRight: isMe ? Radius.zero : const Radius.circular(16),
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            msg['text'],
                            style: const TextStyle(color: Colors.white, fontSize: 14),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            msg['time'],
                            style: TextStyle(color: Colors.white.withOpacity(0.5), fontSize: 9),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),

            // Input field
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: const BoxDecoration(color: AppTheme.slateCard),
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.add, color: Colors.white),
                    onPressed: () {},
                  ),
                  Expanded(
                    child: TextField(
                      controller: _messageController,
                      style: const TextStyle(color: Colors.white),
                      decoration: const InputDecoration(
                        hintText: 'Type WhatsApp reply...',
                        hintStyle: TextStyle(color: AppTheme.textSecondaryDark),
                        border: InputBorder.none,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.send, color: AppTheme.primaryPurple),
                    onPressed: _sendMessage,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _openCRMSidebar(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppTheme.slateCard,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.75,
          maxChildSize: 0.90,
          minChildSize: 0.4,
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
                      decoration: BoxDecoration(color: AppTheme.slateBorder, borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // CRM Header details
                  Text(
                    'LinkPilot CRM Intel',
                    style: GoogleFonts.outfit(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white),
                  ),
                  const SizedBox(height: 16),

                  // Info list
                  _buildCrmRow('Contact Name', widget.thread['name']),
                  _buildCrmRow('Company', widget.thread['company']),
                  _buildCrmRow('Deal Valuation', widget.thread['deal_value']),
                  _buildCrmRow('Lead Priority Score', '${widget.thread['lead_score']}/100'),
                  const Divider(color: AppTheme.slateBorder, height: 32),

                  // AI Insights Card
                  Text('Sentiment & Next Best Action', style: GoogleFonts.outfit(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
                  const SizedBox(height: 12),
                  Container(
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
