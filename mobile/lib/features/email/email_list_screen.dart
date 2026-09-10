import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/providers/providers.dart';
import '../../core/theme/theme.dart';

class EmailListScreen extends ConsumerStatefulWidget {
  const EmailListScreen({super.key});

  @override
  ConsumerState<EmailListScreen> createState() => _EmailListScreenState();
}

class _EmailListScreenState extends ConsumerState<EmailListScreen> {
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounceTimer;
  String _searchQuery = '';
  String _activeTab = 'inbox';

  final List<Map<String, String>> _tabs = const [
    {'id': 'inbox', 'label': 'Inbox'},
    {'id': 'sent', 'label': 'Sent'},
    {'id': 'starred', 'label': 'Starred'},
    {'id': 'archived', 'label': 'Archived'},
  ];

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

  void _openEmailDetail(BuildContext context, Map<String, dynamic> email) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => EmailDetailModal(email: email),
    );
  }

  void _openComposeSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const ComposeEmailModal(),
    ).then((_) => ref.invalidate(mobileEmailsProvider(_searchQuery)));
  }

  @override
  Widget build(BuildContext context) {
    final emailsAsync = ref.watch(mobileEmailsProvider(_searchQuery));
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.bgSlate,
      appBar: AppBar(
        title: const Text('Mail', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 24)),
        actions: [
          IconButton(
            icon: const Icon(Icons.search_rounded, size: 24),
            onPressed: () {},
          ),
        ],
      ),
      body: Column(
        children: [
          // Search Input (Mockup 6)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Search emails',
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

          // Filter Pill Bar: Inbox, Sent, Starred, Archived (Mockup 6)
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
            child: Row(
              children: _tabs.map((tab) {
                final isSelected = _activeTab == tab['id'];
                return Padding(
                  padding: const EdgeInsets.only(right: 8.0),
                  child: ChoiceChip(
                    label: Text(tab['label']!),
                    selected: isSelected,
                    selectedColor: AppTheme.brandBlue,
                    backgroundColor: isDark ? AppTheme.slateCard : const Color(0xFFF1F5F9),
                    side: BorderSide.none,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    labelStyle: TextStyle(
                      color: isSelected ? Colors.white : (isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight),
                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                      fontSize: 13,
                    ),
                    onSelected: (selected) {
                      if (selected) {
                        setState(() {
                          _activeTab = tab['id']!;
                        });
                      }
                    },
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 6),

          // Email List
          Expanded(
            child: emailsAsync.when(
              data: (data) {
                final emails = (data['emails'] as List<dynamic>?) ?? [];
                if (emails.isEmpty) {
                  return _buildMockEmailList(context);
                }

                return RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(mobileEmailsProvider(_searchQuery));
                  },
                  child: ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
                    itemCount: emails.length,
                    itemBuilder: (context, index) {
                      final email = emails[index] as Map<String, dynamic>;
                      return _buildEmailTile(context, email);
                    },
                  ),
                );
              },
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (_, __) => _buildMockEmailList(context),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openComposeSheet(context),
        backgroundColor: AppTheme.primaryNavy,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: const Icon(Icons.edit_rounded, color: Colors.white),
      ),
    );
  }

  Widget _buildMockEmailList(BuildContext context) {
    final mockEmails = [
      {
        'sender_name': 'Sarah Johnson',
        'subject': 'Re: Project Update',
        'body_text': 'Please find the updated contract and timeline details attached for your review.',
        'received_date': '10:24 AM',
        'is_read': 0,
      },
      {
        'sender_name': 'David Miller',
        'subject': 'Contract Review',
        'body_text': 'Just confirming our meeting for tomorrow morning to review the new contract terms.',
        'received_date': '9:15 AM',
        'is_read': 1,
      },
      {
        'sender_name': 'Michael Chen',
        'subject': 'Meeting Tomorrow',
        'body_text': 'Just confirming our meeting...',
        'received_date': '8:32 AM',
        'is_read': 1,
      },
      {
        'sender_name': 'noreply@stripe.com',
        'subject': 'Payment successful',
        'body_text': 'Your payment of \$199 has been processed successfully.',
        'received_date': 'Yesterday',
        'is_read': 1,
      },
      {
        'sender_name': 'LinkedIn',
        'subject': 'You have 3 new notifications',
        'body_text': 'See what\'s new in your network today.',
        'received_date': 'Yesterday',
        'is_read': 1,
      },
    ];

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
      itemCount: mockEmails.length,
      itemBuilder: (context, index) {
        return _buildEmailTile(context, mockEmails[index]);
      },
    );
  }

  Widget _buildEmailTile(BuildContext context, Map<String, dynamic> email) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isUnread = (email['is_read'] ?? 0) == 0;
    final sender = email['sender_name'] ?? 'Sender';
    final subject = email['subject'] ?? 'Subject';
    final preview = email['body_text'] ?? '';
    final time = email['received_date'] ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: InkWell(
        onTap: () => _openEmailDetail(context, email),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Blue dot indicator for unread (Mockup 6)
            if (isUnread) ...[
              Container(
                margin: const EdgeInsets.only(top: 6, right: 10),
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: AppTheme.brandBlue,
                  shape: BoxShape.circle,
                ),
              ),
            ] else ...[
              const SizedBox(width: 18),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        sender,
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: isUnread ? FontWeight.w800 : FontWeight.w600,
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
                    subject,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: isUnread ? FontWeight.w700 : FontWeight.w500,
                      color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    preview,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppTheme.textSecondaryLight,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class EmailDetailModal extends StatelessWidget {
  final Map<String, dynamic> email;
  const EmailDetailModal({super.key, required this.email});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final senderName = email['sender_name'] ?? 'Sarah Johnson';
    final senderEmail = email['sender_email'] ?? 'sarah@company.com';
    final subject = email['subject'] ?? 'Re: Project Update';
    final time = email['received_date'] ?? '10:24 AM';
    final body = email['body_text'] ??
        "Hi there,\n\nLet's schedule a call to discuss the project update. I've also attached the latest files for your review.\n\nLooking forward to your thoughts.\n\nBest regards,\nSarah";

    return Container(
      height: MediaQuery.of(context).size.height * 0.88,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Action Bar (Mockup 7)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
                onPressed: () => Navigator.pop(context),
              ),
              Row(
                children: [
                  IconButton(icon: const Icon(Icons.delete_outline_rounded, size: 20), onPressed: () {}),
                  IconButton(icon: const Icon(Icons.mark_as_unread_outlined, size: 20), onPressed: () {}),
                  IconButton(icon: const Icon(Icons.star_outline_rounded, size: 20), onPressed: () {}),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Subject Title
          Text(
            subject,
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
            ),
          ),
          const SizedBox(height: 16),

          // Sender Info Row
          Row(
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: AppTheme.brandBlue.withValues(alpha: 0.15),
                child: Text(
                  senderName[0].toUpperCase(),
                  style: const TextStyle(color: AppTheme.brandBlue, fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      senderName,
                      style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                    ),
                    Text(
                      senderEmail,
                      style: const TextStyle(fontSize: 12, color: AppTheme.textSecondaryLight),
                    ),
                  ],
                ),
              ),
              Text(
                time,
                style: const TextStyle(fontSize: 12, color: AppTheme.textSecondaryLight),
              ),
            ],
          ),
          const SizedBox(height: 20),
          const Divider(height: 1),
          const SizedBox(height: 16),

          // Body Text
          Expanded(
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    body,
                    style: TextStyle(
                      fontSize: 14,
                      height: 1.6,
                      color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                    ),
                  ),
                  const SizedBox(height: 24),

                  // 2 Attachments (Mockup 7)
                  const Text(
                    '2 attachments',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppTheme.textSecondaryLight),
                  ),
                  const SizedBox(height: 10),

                  _buildAttachmentCard(context, name: 'Project_Plan.pdf', size: '2.4 MB', iconColor: AppTheme.redPriority),
                  const SizedBox(height: 8),
                  _buildAttachmentCard(context, name: 'Timeline.xlsx', size: '1.1 MB', iconColor: AppTheme.greenWhatsApp),
                ],
              ),
            ),
          ),

          // Bottom Action Bar: Reply, Reply All, Forward (Mockup 7)
          Container(
            padding: const EdgeInsets.only(top: 12),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.reply_rounded, size: 16),
                    label: const Text('Reply'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.reply_all_rounded, size: 16),
                    label: const Text('Reply All'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.forward_rounded, size: 16),
                    label: const Text('Forward'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAttachmentCard(BuildContext context, {required String name, required String size, required Color iconColor}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(Icons.insert_drive_file_rounded, color: iconColor, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                Text(size, style: const TextStyle(fontSize: 11, color: AppTheme.textSecondaryLight)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class ComposeEmailModal extends ConsumerStatefulWidget {
  const ComposeEmailModal({super.key});

  @override
  ConsumerState<ComposeEmailModal> createState() => _ComposeEmailModalState();
}

class _ComposeEmailModalState extends ConsumerState<ComposeEmailModal> {
  final _recipientController = TextEditingController();
  final _subjectController = TextEditingController();
  final _bodyController = TextEditingController();
  bool _isSending = false;

  @override
  void dispose() {
    _recipientController.dispose();
    _subjectController.dispose();
    _bodyController.dispose();
    super.dispose();
  }

  Future<void> _sendEmail() async {
    final recipient = _recipientController.text.trim();
    final subject = _subjectController.text.trim();
    final body = _bodyController.text.trim();

    if (recipient.isEmpty || subject.isEmpty || body.isEmpty) return;

    setState(() => _isSending = true);

    try {
      await ref.read(apiClientProvider).sendEmail(recipient, subject, body);
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to send email: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isSending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: isDark ? AppTheme.slateCard : Colors.white,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Compose Email', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                  IconButton(
                    icon: const Icon(Icons.close_rounded),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _recipientController,
                decoration: const InputDecoration(labelText: 'To', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _subjectController,
                decoration: const InputDecoration(labelText: 'Subject', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _bodyController,
                maxLines: 4,
                decoration: const InputDecoration(labelText: 'Message', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: _isSending ? null : _sendEmail,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primaryNavy,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: _isSending
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Send Email', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
