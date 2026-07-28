import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/bottom_nav.dart';
import '../../core/widgets/ai_floating_button.dart';
import '../../core/providers/providers.dart';

class EmailListScreen extends ConsumerStatefulWidget {
  const EmailListScreen({super.key});

  @override
  ConsumerState<EmailListScreen> createState() => _EmailListScreenState();
}

class _EmailListScreenState extends ConsumerState<EmailListScreen> {
  String _selectedFolder = 'Inbox';
  String _selectedFilter = 'Priority';
  final List<String> _filters = ['Priority', 'Unread', 'All', 'Starred', 'Needs Reply'];

  // Dummy email data mirroring production LinkPilot inbox
  final List<Map<String, dynamic>> _dummyEmails = [
    {
      'id': 101,
      'sender_name': 'Sarah Jenkins',
      'sender_email': 'sarah@attio.com',
      'subject': 'Attio CRM Integration Proposal',
      'body_text': 'Hi, I reviewed the custom API webhook schemas you sent yesterday. Let\'s coordinate a quick call at 2:00 PM today to finalize our data sync contract and credentials...',
      'received_date': '10:45 AM',
      'is_read': false,
      'is_starred': true,
      'priority': 'high',
      'ai_summary': 'Sarah reviewed webhooks and proposed a meeting at 2:00 PM to finalize Attio integration sync.',
      'ai_suggested_reply': 'Hi Sarah, 2:00 PM works perfectly for me. I\'ll send over the Google Meet invite shortly.',
    },
    {
      'id': 102,
      'sender_name': 'Github Notifications',
      'sender_email': 'noreply@github.com',
      'subject': '[GitHub] Security Alert: 3 dependencies require updates',
      'body_text': 'We found security vulnerabilities in dependencies of wbsoumo/linkpilot. Please upgrade packages as listed in security tab...',
      'received_date': 'Yesterday',
      'is_read': true,
      'is_starred': false,
      'priority': 'medium',
      'ai_summary': 'Dependabot security alerts detected in three repository libraries.',
      'ai_suggested_reply': 'Acknowledge security alert. Will audit dependencies package.json lockfile.',
    },
    {
      'id': 103,
      'sender_name': 'Novexa Merchant Support',
      'sender_email': 'support@novexapay.com',
      'subject': 'Merchant Account Verification Complete',
      'body_text': 'Dear LinkPilot Partner, we are pleased to inform you that your Merchant API access has been fully verified and activated. You can now process real-time customer invoice recharges...',
      'received_date': '26 Jul',
      'is_read': false,
      'is_starred': false,
      'priority': 'high',
      'ai_summary': 'Novexa merchant API account has been verified and wallet recharge routes are open.',
      'ai_suggested_reply': 'Thanks for the confirmation. We\'ll begin API testing immediately.',
    },
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _selectedFolder,
          style: GoogleFonts.outfit(fontWeight: FontWeight.bold, color: Colors.white),
        ),
        backgroundColor: AppTheme.obsidianBlack,
        actions: [
          IconButton(
            icon: const Icon(Icons.search, color: Colors.white),
            onPressed: () {},
          ),
          IconButton(
            icon: const Icon(Icons.edit_note, color: Colors.white, size: 28),
            onPressed: () => _openComposeBottomSheet(context),
          ),
        ],
      ),
      drawer: _buildDrawer(),
      body: Container(
        decoration: const BoxDecoration(
          gradient: AppTheme.premiumDarkGradient,
        ),
        child: Column(
          children: [
            // Folder Filter Row
            Container(
              height: 48,
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: _filters.length,
                itemBuilder: (context, index) {
                  final filter = _filters[index];
                  final isSelected = _selectedFilter == filter;
                  return GestureDetector(
                    onTap: () {
                      setState(() {
                        _selectedFilter = filter;
                      });
                    },
                    child: Container(
                      margin: const EdgeInsets.only(right: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                      decoration: BoxDecoration(
                        color: isSelected ? AppTheme.primaryPurple : AppTheme.slateCard,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: isSelected ? AppTheme.primaryPurple : AppTheme.slateBorder,
                        ),
                      ),
                      child: Center(
                        child: Text(
                          filter,
                          style: TextStyle(
                            color: isSelected ? Colors.white : AppTheme.textSecondaryDark,
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),

            // Email Swipe List
            Expanded(
              child: ref.watch(emailsListProvider(_selectedFolder.toLowerCase())).when(
                data: (emails) {
                  final listToDisplay = emails.isNotEmpty ? emails : _dummyEmails;

                  return ListView.builder(
                    itemCount: listToDisplay.length,
                    itemBuilder: (context, index) {
                      final item = listToDisplay[index];
                      final email = {
                        'id': item['id'] ?? index,
                        'sender_name': item['sender_name'] ?? item['sender_email'] ?? 'Unknown',
                        'sender_email': item['sender_email'] ?? '',
                        'subject': item['subject'] ?? '(No Subject)',
                        'body_text': item['body_text'] ?? item['body_html'] ?? '',
                        'received_date': item['received_date'] ?? 'Just now',
                        'is_read': item['is_read'] == 1 || item['is_read'] == true,
                        'is_starred': item['is_starred'] == 1 || item['is_starred'] == true,
                        'priority': item['priority'] ?? 'medium',
                        'ai_summary': item['ai_summary'] ?? '',
                        'ai_suggested_reply': item['ai_suggested_reply'] ?? '',
                      };
                      return Dismissible(
                        key: Key(email['id'].toString()),
                        background: Container(
                          color: Colors.green,
                          alignment: Alignment.centerLeft,
                          padding: const EdgeInsets.only(left: 20),
                          child: const Icon(Icons.archive, color: Colors.white),
                        ),
                        secondaryBackground: Container(
                          color: AppTheme.priorityOrange,
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.only(right: 20),
                          child: const Icon(Icons.delete, color: Colors.white),
                        ),
                        onDismissed: (direction) {
                          if (emails.isNotEmpty) {
                            ref.read(apiClientProvider).updateEmailState(
                              email['id'] as int,
                              direction == DismissDirection.startToEnd ? 'archived' : 'delete',
                              1,
                            );
                          }
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(
                                direction == DismissDirection.startToEnd
                                    ? 'Email Archived'
                                    : 'Email Deleted',
                              ),
                            ),
                          );
                        },
                        child: GestureDetector(
                          onTap: () => _openEmailDetailSheet(context, email),
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
                            decoration: BoxDecoration(
                              border: Border(
                                bottom: BorderSide(color: Colors.white.withOpacity(0.05)),
                              ),
                              color: email['is_read'] ? Colors.transparent : Colors.white.withOpacity(0.02),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                // Read/Unread circle indicator
                                Container(
                                  margin: const EdgeInsets.only(top: 6),
                                  width: 8,
                                  height: 8,
                                  decoration: BoxDecoration(
                                    color: email['is_read'] ? Colors.transparent : AppTheme.primaryPurple,
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 12),

                                // Content details
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Text(
                                            email['sender_name'],
                                            style: TextStyle(
                                              fontWeight: email['is_read'] ? FontWeight.normal : FontWeight.bold,
                                              color: Colors.white,
                                              fontSize: 15,
                                            ),
                                          ),
                                          Text(
                                            email['received_date'],
                                            style: const TextStyle(
                                              color: AppTheme.textSecondaryDark,
                                              fontSize: 12,
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        email['subject'],
                                        style: TextStyle(
                                          color: Colors.white,
                                          fontSize: 14,
                                          fontWeight: email['is_read'] ? FontWeight.w500 : FontWeight.bold,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        email['body_text'],
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          color: AppTheme.textSecondaryDark,
                                          fontSize: 13,
                                        ),
                                      ),
                                      const SizedBox(height: 8),

                                      // Priority pill
                                      if (email['priority'] == 'high')
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: AppTheme.priorityOrange.withOpacity(0.15),
                                            borderRadius: BorderRadius.circular(4),
                                            border: Border.all(color: AppTheme.priorityOrange.withOpacity(0.5)),
                                          ),
                                          child: const Text(
                                            'Priority',
                                            style: TextStyle(color: AppTheme.priorityOrange, fontSize: 10, fontWeight: FontWeight.bold),
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  );
                },
                loading: () => const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24.0),
                    child: CircularProgressIndicator(color: AppTheme.primaryPurple),
                  ),
                ),
                error: (err, stack) => const Center(
                  child: Text('Error connecting to live inbox.', style: TextStyle(color: AppTheme.textSecondaryDark)),
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: const AiFloatingActionButton(),
      bottomNavigationBar: const LinkPilotBottomNav(currentIndex: 1),
    );
  }

  Widget _buildDrawer() {
    return Drawer(
      backgroundColor: AppTheme.obsidianBlack,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          DrawerHeader(
            decoration: const BoxDecoration(color: AppTheme.slateCard),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.radar_rounded, color: AppTheme.primaryPurple, size: 40),
                const SizedBox(height: 12),
                Text(
                  'LinkPilot Mailboxes',
                  style: GoogleFonts.outfit(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                ),
              ],
            ),
          ),
          _buildDrawerItem(Icons.inbox, 'Inbox'),
          _buildDrawerItem(Icons.star_border, 'Starred'),
          _buildDrawerItem(Icons.send_outlined, 'Sent'),
          _buildDrawerItem(Icons.drafts_outlined, 'Drafts'),
          _buildDrawerItem(Icons.archive_outlined, 'Archive'),
          _buildDrawerItem(Icons.delete_outline, 'Trash'),
        ],
      ),
    );
  }

  Widget _buildDrawerItem(IconData icon, String title) {
    final isSelected = _selectedFolder == title;
    return ListTile(
      leading: Icon(icon, color: isSelected ? AppTheme.primaryPurple : AppTheme.textSecondaryDark),
      title: Text(
        title,
        style: TextStyle(color: isSelected ? Colors.white : AppTheme.textSecondaryDark, fontWeight: isSelected ? FontWeight.bold : FontWeight.normal),
      ),
      selected: isSelected,
      onTap: () {
        setState(() {
          _selectedFolder = title;
        });
        Navigator.pop(context);
      },
    );
  }

  void _openEmailDetailSheet(BuildContext context, Map<String, dynamic> email) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppTheme.slateCard,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return DraggableScrollableSheet(
          initialChildSize: 0.85,
          maxChildSize: 0.95,
          minChildSize: 0.5,
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

                  // Header details
                  Text(
                    email['subject'],
                    style: GoogleFonts.outfit(
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            email['sender_name'],
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                          ),
                          Text(
                            email['sender_email'],
                            style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
                          ),
                        ],
                      ),
                      Text(
                        email['received_date'],
                        style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
                      ),
                    ],
                  ),
                  const Divider(color: AppTheme.slateBorder, height: 32),

                  // Body
                  Text(
                    email['body_text'],
                    style: const TextStyle(color: Colors.white, fontSize: 15, height: 1.6),
                  ),
                  const Divider(color: AppTheme.slateBorder, height: 32),

                  // AI Copilot Actions Section
                  Text(
                    'AI Intelligence',
                    style: GoogleFonts.outfit(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Summary Box
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: AppTheme.glassBox(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(Icons.summarize_outlined, color: AppTheme.primaryPurple, size: 20),
                            SizedBox(width: 8),
                            Text(
                              'AI Semantic Summary',
                              style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          email['ai_summary'] ?? 'No summary generated.',
                          style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 13, height: 1.4),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Auto-suggested reply Box
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: AppTheme.glassBox(),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(Icons.bolt, color: Colors.orangeAccent, size: 20),
                            SizedBox(width: 8),
                            Text(
                              'Suggested Smart Reply',
                              style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          email['ai_suggested_reply'] ?? 'No draft prepared.',
                          style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 13, height: 1.4),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            OutlinedButton(
                              onPressed: () {},
                              style: OutlinedButton.styleFrom(
                                side: const BorderSide(color: AppTheme.slateBorder),
                              ),
                              child: const Text('Rewrite', style: TextStyle(color: Colors.white)),
                            ),
                            const SizedBox(width: 8),
                            ElevatedButton(
                              onPressed: () {
                                Navigator.pop(context);
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(content: Text('Auto-reply sent successfully!')),
                                );
                              },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppTheme.primaryPurple,
                              ),
                              child: const Text('Send Draft', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                            ),
                          ],
                        ),
                      ],
                    ),
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

  void _openComposeBottomSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppTheme.slateCard,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
            top: 24,
            left: 24,
            right: 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'New Message',
                style: GoogleFonts.outfit(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
              ),
              const SizedBox(height: 16),
              TextField(
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: 'Recipient Email',
                  hintStyle: const TextStyle(color: AppTheme.textSecondaryDark),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.03),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: 'Subject',
                  hintStyle: const TextStyle(color: AppTheme.textSecondaryDark),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.03),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                maxLines: 6,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: 'Type your message or use AI to draft...',
                  hintStyle: const TextStyle(color: AppTheme.textSecondaryDark),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.03),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  OutlinedButton.icon(
                    onPressed: () {},
                    icon: const Icon(Icons.auto_awesome, color: Colors.orangeAccent, size: 18),
                    label: const Text('AI Draft', style: TextStyle(color: Colors.white)),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: AppTheme.slateBorder),
                    ),
                  ),
                  ElevatedButton(
                    onPressed: () {
                      Navigator.pop(context);
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Message sent!')),
                      );
                    },
                    style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primaryPurple),
                    child: const Text('Send', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
              const SizedBox(height: 24),
            ],
          ),
        );
      },
    );
  }
}
