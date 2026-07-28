import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/bottom_nav.dart';
import '../../core/widgets/ai_floating_button.dart';
import '../../core/providers/providers.dart';
import '../whatsapp/whatsapp_inbox_screen.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  String _selectedTimeline = 'This Week';
  String _activeAnalyticsTab = 'Emails';

  void _openSearchDialog(BuildContext context) {
    final searchController = TextEditingController();
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Search Workspace'),
        content: TextField(
          controller: searchController,
          autofocus: true,
          decoration: const InputDecoration(
            hintText: 'Search leads, emails, messages...',
            prefixIcon: Icon(Icons.search),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () {
              final query = searchController.text.trim();
              Navigator.pop(context);
              if (query.isNotEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Searching for "$query"...')),
                );
              }
            },
            child: const Text('Search'),
          ),
        ],
      ),
    );
  }

  void _openNotificationsDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: const [
            Icon(Icons.notifications_active, color: Colors.blueAccent),
            SizedBox(width: 8),
            Text('Workspace Updates'),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const CircleAvatar(child: Icon(Icons.forum, size: 16)),
              title: const Text('WhatsApp Message Received'),
              subtitle: const Text('Soumojit Saha sent a new webhook confirmation link.'),
              onTap: () {
                Navigator.pop(context);
                context.go('/whatsapp');
              },
            ),
            const Divider(),
            ListTile(
              leading: const CircleAvatar(child: Icon(Icons.auto_awesome, size: 16)),
              title: const Text('AI Autopilot Auto-Replied'),
              subtitle: const Text('Successfully responded to Priya Mehta’s proposal request.'),
              onTap: () {
                Navigator.pop(context);
                context.go('/whatsapp');
              },
            ),
            const Divider(),
            ListTile(
              leading: const CircleAvatar(child: Icon(Icons.assignment, size: 16)),
              title: const Text('Task Due Today'),
              subtitle: const Text('Quarterly sales report submission for Novexa Pay.'),
              onTap: () => Navigator.pop(context),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  void _openTimelineSelector(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return Container(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Select Timeline Range',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              ListTile(
                title: const Text('Today'),
                selected: _selectedTimeline == 'Today',
                onTap: () {
                  setState(() {
                    _selectedTimeline = 'Today';
                  });
                  Navigator.pop(context);
                },
              ),
              ListTile(
                title: const Text('This Week'),
                selected: _selectedTimeline == 'This Week',
                onTap: () {
                  setState(() {
                    _selectedTimeline = 'This Week';
                  });
                  Navigator.pop(context);
                },
              ),
              ListTile(
                title: const Text('This Month'),
                selected: _selectedTimeline == 'This Month',
                onTap: () {
                  setState(() {
                    _selectedTimeline = 'This Month';
                  });
                  Navigator.pop(context);
                },
              ),
            ],
          ),
        );
      },
    );
  }

  void _openAiInsightsBottomSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).cardColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.withOpacity(0.3),
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  const Icon(Icons.auto_awesome, color: Colors.purpleAccent, size: 24),
                  const SizedBox(width: 8),
                  Text(
                    'LinkPilot AI Copilot Intel',
                    style: GoogleFonts.outfit(fontSize: 20, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'Based on your workspace activity this week:',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 12),
              _buildInsightRow('Priority Emails', '12 critical emails detected from potential buyers.'),
              _buildInsightRow('WhatsApp Autopilot', 'AI Bot is connected and resolved 8 customer questions.'),
              _buildInsightRow('Lead conversion probability', '5 contacts match top buyer criteria with scoring above 88%.'),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryPurple,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () {
                    Navigator.pop(context);
                    context.go('/emails');
                  },
                  child: const Text('Open Priority Inbox', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildInsightRow(String title, String description) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8.0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.check_circle_outline, color: Colors.green, size: 18),
          const SizedBox(width: 10),
          Expanded(
            child: RichText(
              text: TextSpan(
                style: TextStyle(color: Theme.of(context).textTheme.bodyMedium?.color, fontSize: 13, height: 1.4),
                children: [
                  TextSpan(text: '$title: ', style: const TextStyle(fontWeight: FontWeight.bold)),
                  TextSpan(text: description),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _openChat(String name, String id, String company, String dealValue, String leadScore) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => WhatsAppChatScreen(
          thread: {
            'id': id,
            'name': name,
            'status': 'Online',
            'company': company,
            'deal_value': dealValue,
            'lead_score': leadScore,
          },
        ),
      ),
    );
  }

  void _openQuickActionNewContact(BuildContext context) {
    final nameCtrl = TextEditingController();
    final emailCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Add New Contact'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name')),
            TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email Address')),
            TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone Number')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final name = nameCtrl.text.trim();
              Navigator.pop(context);
              if (name.isNotEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Contact "$name" created successfully!')),
                );
              }
            },
            child: const Text('Create'),
          ),
        ],
      ),
    );
  }

  void _openQuickActionAddDeal(BuildContext context) {
    final titleCtrl = TextEditingController();
    final valueCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Add CRM Deal'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Deal Title')),
            TextField(controller: valueCtrl, decoration: const InputDecoration(labelText: 'Value (e.g. ₹50,000)')),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final title = titleCtrl.text.trim();
              Navigator.pop(context);
              if (title.isNotEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Deal "$title" added to pipeline!')),
                );
              }
            },
            child: const Text('Add'),
          ),
        ],
      ),
    );
  }

  void _openQuickActionScheduleMeeting(BuildContext context) async {
    final date = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null) return;

    if (!context.mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );
    if (time == null) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Meeting scheduled for ${date.toLocal().toString().split(' ')[0]} at ${time.format(context)}!')),
    );
  }

  void _openQuickActionCreateTask(BuildContext context) {
    final titleCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Create New Task'),
        content: TextField(
          controller: titleCtrl,
          decoration: const InputDecoration(labelText: 'Task Title'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final title = titleCtrl.text.trim();
              Navigator.pop(context);
              if (title.isNotEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Task "$title" created!')),
                );
              }
            },
            child: const Text('Create'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authStateProvider);
    final user = authState.user;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;
    final cardBg = isDark ? AppTheme.slateCard : Colors.white;
    final cardBorder = isDark ? AppTheme.slateBorder : Colors.black.withOpacity(0.04);

    final dashboardAsync = ref.watch(dashboardDataProvider);
    final emailsAsync = ref.watch(emailsListProvider('inbox'));
    final whatsappThreadsAsync = ref.watch(whatsappThreadsProvider);
    final contactsAsync = ref.watch(crmContactsProvider);
    final dealsAsync = ref.watch(crmDealsProvider);

    final totalEmailsStr = dashboardAsync.when(
      data: (data) => data['statistics']?['emails_received']?.toString() ?? '0',
      loading: () => '...',
      error: (_, __) => emailsAsync.when(
        data: (list) => list.length.toString(),
        loading: () => '...',
        error: (_, __) => '1,248',
      ),
    );

    final totalChatsStr = whatsappThreadsAsync.when(
      data: (list) => list.length.toString(),
      loading: () => '...',
      error: (_, __) => '342',
    );

    final totalLeadsStr = contactsAsync.when(
      data: (list) => list.length.toString(),
      loading: () => '...',
      error: (_, __) => '86',
    );

    final totalDealsStr = dealsAsync.when(
      data: (data) {
        final stagesData = data['stages'] as Map<String, dynamic>? ?? {};
        final wonList = stagesData['Closed Won'] as List<dynamic>? ?? [];
        if (wonList.isEmpty) return '0';
        
        double totalWon = 0;
        for (var d in wonList) {
          final val = double.tryParse(d['value']?.toString() ?? '') ?? 0.0;
          totalWon += val;
        }
        if (totalWon > 0) {
          if (totalWon >= 100000) {
            return '₹${(totalWon / 100000).toStringAsFixed(1)}L';
          } else {
            return '₹${totalWon.toStringAsFixed(0)}';
          }
        }
        return wonList.length.toString();
      },
      loading: () => '...',
      error: (_, __) => '₹2.45L',
    );

    // Resolve Spline values dynamically based on selected tab
    List<double> splinePoints = [0.8, 0.5, 0.6, 0.4, 0.7, 0.5, 0.75];
    String chartTooltip = totalEmailsStr;
    if (_activeAnalyticsTab == 'WhatsApp') {
      splinePoints = [0.4, 0.6, 0.3, 0.7, 0.5, 0.8, 0.6];
      chartTooltip = totalChatsStr;
    } else if (_activeAnalyticsTab == 'Leads') {
      splinePoints = [0.9, 0.7, 0.8, 0.5, 0.6, 0.4, 0.3];
      chartTooltip = totalLeadsStr;
    } else if (_activeAnalyticsTab == 'Deals') {
      splinePoints = [0.2, 0.4, 0.3, 0.6, 0.5, 0.7, 0.8];
      chartTooltip = totalDealsStr;
    }

    return Scaffold(
      backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.iceWhite,
      appBar: AppBar(
        backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.iceWhite,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.menu, color: textPrimary),
          onPressed: () {},
        ),
        titleSpacing: 0,
        title: Row(
          children: [
            Text(
              'LinkPilot',
              style: GoogleFonts.outfit(
                fontWeight: FontWeight.bold,
                fontSize: 20,
                color: textPrimary,
              ),
            ),
            const SizedBox(width: 4),
            Container(
              width: 6,
              height: 6,
              decoration: const BoxDecoration(
                color: Colors.blueAccent,
                shape: BoxShape.circle,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: Icon(Icons.search, color: textPrimary, size: 22),
            onPressed: () => _openSearchDialog(context),
          ),
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: Icon(Icons.notifications_none_outlined, color: textPrimary, size: 24),
                onPressed: () => _openNotificationsDialog(context),
              ),
              Positioned(
                right: 8,
                top: 8,
                child: Container(
                  padding: const EdgeInsets.all(4),
                  decoration: const BoxDecoration(
                    color: Colors.blueAccent,
                    shape: BoxShape.circle,
                  ),
                  child: const Text(
                    '3',
                    style: TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.only(right: 16.0, left: 8.0),
            child: GestureDetector(
              onTap: () => context.go('/settings'),
              child: Stack(
                children: [
                  const CircleAvatar(
                    radius: 18,
                    backgroundImage: NetworkImage(
                      'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=100&q=80',
                    ),
                  ),
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
            ),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Greeting row
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Good Morning, ${user?['name']?.split(' ').first ?? 'Soumojit'} 👋',
                        style: GoogleFonts.outfit(
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                          color: textPrimary,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        "Here's what's happening with your workspace today.",
                        style: TextStyle(
                          fontSize: 12,
                          color: textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                GestureDetector(
                  onTap: () => _openTimelineSelector(context),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: cardBg,
                      border: Border.all(color: cardBorder),
                      borderRadius: BorderRadius.circular(12),
                      boxShadow: [
                        BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 4),
                      ],
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.calendar_today_outlined, size: 14, color: textSecondary),
                        const SizedBox(width: 6),
                        Text(
                          _selectedTimeline,
                          style: TextStyle(color: textPrimary, fontSize: 12, fontWeight: FontWeight.w500),
                        ),
                        const SizedBox(width: 4),
                        Icon(Icons.keyboard_arrow_down, size: 14, color: textSecondary),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Horizontal Scrolling 4 Stats Cards
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  GestureDetector(
                    onTap: () => context.go('/emails'),
                    child: _buildStatCard(
                      context: context,
                      icon: Icons.email_outlined,
                      iconColor: Colors.deepPurple,
                      bgColor: Colors.deepPurple.withOpacity(0.08),
                      title: 'Total Emails',
                      value: totalEmailsStr,
                      percentage: '12.5%',
                      trendUp: true,
                    ),
                  ),
                  GestureDetector(
                    onTap: () => context.go('/whatsapp'),
                    child: _buildStatCard(
                      context: context,
                      icon: Icons.send_rounded,
                      iconColor: Colors.teal,
                      bgColor: Colors.teal.withOpacity(0.08),
                      title: 'WhatsApp Chats',
                      value: totalChatsStr,
                      percentage: '8.4%',
                      trendUp: true,
                    ),
                  ),
                  _buildStatCard(
                    context: context,
                    icon: Icons.workspace_premium_outlined,
                    iconColor: Colors.amber[800]!,
                    bgColor: Colors.amber.withOpacity(0.08),
                    title: 'New Leads',
                    value: totalLeadsStr,
                    percentage: '15.2%',
                    trendUp: true,
                  ),
                  _buildStatCard(
                    context: context,
                    icon: Icons.monetization_on_outlined,
                    iconColor: Colors.blueAccent,
                    bgColor: Colors.blueAccent.withOpacity(0.08),
                    title: 'Deals Won',
                    value: totalDealsStr,
                    percentage: '18.7%',
                    trendUp: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // AI Insight Banner (Beta)
            GestureDetector(
              onTap: () => _openAiInsightsBottomSheet(context),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: isDark 
                        ? [Colors.deepPurple.withOpacity(0.15), Colors.blueAccent.withOpacity(0.08)]
                        : [Colors.deepPurple.withOpacity(0.06), Colors.blueAccent.withOpacity(0.03)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  border: Border.all(color: Colors.deepPurple.withOpacity(0.15)),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(colors: [Colors.indigoAccent, Colors.purpleAccent]),
                        borderRadius: BorderRadius.circular(14),
                        boxShadow: [
                          BoxShadow(color: Colors.purpleAccent.withOpacity(0.2), blurRadius: 8),
                        ],
                      ),
                      child: const Icon(Icons.auto_awesome, color: Colors.white, size: 20),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(
                                'AI Insight',
                                style: GoogleFonts.outfit(fontWeight: FontWeight.bold, color: textPrimary, fontSize: 14),
                              ),
                              const SizedBox(width: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: Colors.indigoAccent.withOpacity(0.12),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: const Text(
                                  'Beta',
                                  style: TextStyle(color: Colors.indigoAccent, fontSize: 9, fontWeight: FontWeight.bold),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          RichText(
                            text: TextSpan(
                              style: TextStyle(color: textSecondary, fontSize: 12, height: 1.3),
                              children: const [
                                TextSpan(text: 'You have '),
                                TextSpan(text: '12', style: TextStyle(color: Colors.indigoAccent, fontWeight: FontWeight.bold)),
                                TextSpan(text: ' high-priority emails that need your attention and '),
                                TextSpan(text: '5', style: TextStyle(color: Colors.indigoAccent, fontWeight: FontWeight.bold)),
                                TextSpan(text: ' leads are most likely to convert this week.'),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    Icon(Icons.chevron_right, color: Colors.blueAccent[700], size: 18),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Priority Emails & WhatsApp Overview (Side by Side or Column layout)
            Column(
              children: [
                // Priority Emails Container
                _buildSectionHeader(context, title: 'Priority Emails', actionText: 'View All', onTap: () => context.go('/emails')),
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
                  decoration: BoxDecoration(
                    color: cardBg,
                    border: Border.all(color: cardBorder),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: emailsAsync.when(
                    data: (emails) {
                      if (emails.isEmpty) {
                        return const Padding(
                          padding: EdgeInsets.symmetric(vertical: 24.0),
                          child: Center(
                            child: Text(
                              'No priority emails in inbox.',
                              style: TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
                            ),
                          ),
                        );
                      }
                      final displayEmails = emails.take(5).toList();
                      return Column(
                        children: [
                          ...displayEmails.map((item) {
                            final name = item['sender_name'] ?? item['sender_email'] ?? 'Unknown';
                            final subject = item['subject'] ?? '(No Subject)';
                            final time = item['received_date'] ?? 'Just now';
                            final priority = item['priority'] ?? 'medium';
                            
                            Color priorityColor = Colors.orange;
                            if (priority.toLowerCase() == 'high') {
                              priorityColor = Colors.deepPurple;
                            } else if (priority.toLowerCase() == 'low') {
                              priorityColor = Colors.blue;
                            }
                            
                            final domain = item['sender_email']?.toString().split('@').last ?? 'gmail.com';
                            
                            return _buildEmailItem(
                              logoUrl: 'https://img.logo.dev/$domain?token=pk_N-oU80_cR4CQ8ojWxHTECA',
                              name: name,
                              subject: subject,
                              time: time,
                              priority: priority[0].toUpperCase() + priority.substring(1),
                              priorityColor: priorityColor,
                            );
                          }).toList(),
                          _buildNavigateFooter(context, label: 'Go to Inbox', onTap: () => context.go('/emails')),
                        ],
                      );
                    },
                    loading: () => const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24.0),
                      child: Center(child: CircularProgressIndicator(strokeWidth: 2, color: Colors.blueAccent)),
                    ),
                    error: (err, stack) => Column(
                      children: [
                        _buildEmailItem(
                          logoUrl: 'https://img.logo.dev/gmail.com?token=pk_N-oU80_cR4CQ8ojWxHTECA',
                          name: 'Alex Johnson',
                          subject: 'Proposal for LinkPilot Enterprise',
                          time: '10:24 AM',
                          priority: 'High',
                          priorityColor: Colors.deepPurple,
                        ),
                        _buildEmailItem(
                          logoUrl: 'https://img.logo.dev/microsoft.com?token=pk_N-oU80_cR4CQ8ojWxHTECA',
                          name: 'Mark Thompson',
                          subject: 'Re: Integration Discussion',
                          time: '09:15 AM',
                          priority: 'Medium',
                          priorityColor: Colors.orange,
                        ),
                        _buildNavigateFooter(context, label: 'Go to Inbox', onTap: () => context.go('/emails')),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 20),

                // WhatsApp Overview Container
                _buildSectionHeader(context, title: 'WhatsApp Overview', actionText: 'View All', onTap: () => context.go('/whatsapp')),
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
                  decoration: BoxDecoration(
                    color: cardBg,
                    border: Border.all(color: cardBorder),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: whatsappThreadsAsync.when(
                    data: (threads) {
                      if (threads.isEmpty) {
                        return const Padding(
                          padding: EdgeInsets.symmetric(vertical: 24.0),
                          child: Center(
                            child: Text(
                              'No WhatsApp chats available.',
                              style: TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
                            ),
                          ),
                        );
                      }
                      final displayThreads = threads.take(5).toList();
                      return Column(
                        children: [
                          ...displayThreads.map((item) {
                            final thread = {
                              'id': item['id'],
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
                            
                            bool isApple = thread['name'].toString().toLowerCase().contains('apple');
                            bool isMeera = thread['name'].toString().toLowerCase().contains('meera');
                            bool isSoumojit = thread['name'].toString().toLowerCase().contains('soumojit');
                            bool isPriya = thread['name'].toString().toLowerCase().contains('priya');
                            bool isAshwin = thread['name'].toString().toLowerCase().contains('ashwin');
                            bool isRahul = thread['name'].toString().toLowerCase().contains('rahul');
                            bool isNeha = thread['name'].toString().toLowerCase().contains('neha');
                            
                            String avatarUrl = 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&w=100&q=80';
                            if (isSoumojit) {
                              avatarUrl = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=100&q=80';
                            } else if (isPriya) {
                              avatarUrl = 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&q=80';
                            } else if (isApple) {
                              avatarUrl = 'https://img.logo.dev/apple.com?token=pk_N-oU80_cR4CQ8ojWxHTECA';
                            } else if (isAshwin) {
                              avatarUrl = 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80';
                            } else if (isMeera) {
                              avatarUrl = 'https://img.logo.dev/zara.com?token=pk_N-oU80_cR4CQ8ojWxHTECA';
                            } else if (isRahul) {
                              avatarUrl = 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80';
                            } else if (isNeha) {
                              avatarUrl = 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=100&q=80';
                            }

                            final rawTime = thread['time']?.toString() ?? 'Just now';
                            final displayTime = rawTime.contains(' ') ? rawTime.split(' ').last : rawTime;

                            return GestureDetector(
                              behavior: HitTestBehavior.opaque,
                              onTap: () => _openChat(
                                thread['name'] as String,
                                thread['id'].toString(),
                                thread['company'] as String,
                                thread['deal_value'] as String,
                                thread['lead_score'] as String,
                              ),
                              child: _buildWhatsAppItem(
                                avatarUrl: avatarUrl,
                                name: thread['name'] as String,
                                message: thread['last_message'] as String,
                                time: displayTime,
                                unreadCount: thread['unread_count'] as int,
                              ),
                            );
                          }).toList(),
                          _buildNavigateFooter(context, label: 'Open WhatsApp', onTap: () => context.go('/whatsapp')),
                        ],
                      );
                    },
                    loading: () => const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24.0),
                      child: Center(child: CircularProgressIndicator(strokeWidth: 2, color: Colors.blueAccent)),
                    ),
                    error: (err, stack) => Column(
                      children: [
                        GestureDetector(
                          behavior: HitTestBehavior.opaque,
                          onTap: () => _openChat('Rahul Sharma', '1', 'Novexa Pay', '₹45,000', '94'),
                          child: _buildWhatsAppItem(
                            avatarUrl: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80',
                            name: 'Rahul Sharma',
                            message: 'Thanks! Please send...',
                            time: '10:30 AM',
                            unreadCount: 2,
                          ),
                        ),
                        _buildNavigateFooter(context, label: 'Open WhatsApp', onTap: () => context.go('/whatsapp')),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Analytics Overview Card with Spline chart
            _buildSectionHeader(context, title: 'Analytics Overview', actionText: 'View Report', onTap: () {}),
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: cardBg,
                border: Border.all(color: cardBorder),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          _buildTabButton('Emails'),
                          _buildTabButton('WhatsApp'),
                          _buildTabButton('Leads'),
                          _buildTabButton('Deals'),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    height: 180,
                    child: CustomPaint(
                      size: Size.infinite,
                      painter: SplineChartPainter(
                        isDark: isDark,
                        values: splinePoints,
                        tooltipText: chartTooltip,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Recent Activity Container
            _buildSectionHeader(context, title: 'Recent Activity', actionText: 'View All', onTap: () {}),
            Container(
              padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 16),
              decoration: BoxDecoration(
                color: cardBg,
                border: Border.all(color: cardBorder),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(
                children: [
                  _buildActivityItem(
                    icon: Icons.email_outlined,
                    iconBgColor: Colors.blue.withOpacity(0.08),
                    iconColor: Colors.blueAccent,
                    title: 'New email from Alex Johnson',
                    time: '10:24 AM',
                  ),
                  _buildActivityItem(
                    icon: Icons.forum_outlined,
                    iconBgColor: Colors.green.withOpacity(0.08),
                    iconColor: Colors.green,
                    title: 'New message from Rahul Sharma',
                    time: '10:30 AM',
                  ),
                  _buildActivityItem(
                    icon: Icons.person_add_outlined,
                    iconBgColor: Colors.orange.withOpacity(0.08),
                    iconColor: Colors.orange,
                    title: 'New lead – Global Solutions',
                    time: '09:15 AM',
                  ),
                  _buildActivityItem(
                    icon: Icons.monetization_on_outlined,
                    iconBgColor: Colors.teal.withOpacity(0.08),
                    iconColor: Colors.teal,
                    title: 'Deal Closed – ₹45,000',
                    time: 'Yesterday',
                  ),
                  _buildActivityItem(
                    icon: Icons.calendar_today_outlined,
                    iconBgColor: Colors.purple.withOpacity(0.08),
                    iconColor: Colors.purple,
                    title: 'Meeting with Priya Mehta',
                    time: 'Yesterday',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Quick Actions section
            Text(
              'Quick Actions',
              style: GoogleFonts.outfit(fontWeight: FontWeight.bold, fontSize: 16, color: textPrimary),
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                GestureDetector(
                  onTap: () => context.go('/emails'),
                  child: _buildQuickActionItem(context, Icons.email_outlined, 'Compose Email'),
                ),
                GestureDetector(
                  onTap: () => _openQuickActionNewContact(context),
                  child: _buildQuickActionItem(context, Icons.person_add_outlined, 'New Contact'),
                ),
                GestureDetector(
                  onTap: () => _openQuickActionAddDeal(context),
                  child: _buildQuickActionItem(context, Icons.monetization_on_outlined, 'Add Deal'),
                ),
                GestureDetector(
                  onTap: () => _openQuickActionScheduleMeeting(context),
                  child: _buildQuickActionItem(context, Icons.calendar_today_outlined, 'Schedule Meeting'),
                ),
                GestureDetector(
                  onTap: () => _openQuickActionCreateTask(context),
                  child: _buildQuickActionItem(context, Icons.check_box_outlined, 'Create Task'),
                ),
              ],
            ),
            const SizedBox(height: 40),
          ],
        ),
      ),
      floatingActionButton: const AiFloatingActionButton(),
      bottomNavigationBar: const LinkPilotBottomNav(currentIndex: 0),
    );
  }

  Widget _buildStatCard({
    required BuildContext context,
    required IconData icon,
    required Color iconColor,
    required Color bgColor,
    required String title,
    required String value,
    required String percentage,
    required bool trendUp,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;
    final cardBg = isDark ? AppTheme.slateCard : Colors.white;
    final cardBorder = isDark ? AppTheme.slateBorder : Colors.black.withOpacity(0.04);

    return Container(
      width: 140,
      margin: const EdgeInsets.only(right: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: cardBg,
        border: Border.all(color: cardBorder),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 4),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: bgColor,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: iconColor, size: 20),
          ),
          const SizedBox(height: 14),
          Text(
            title,
            style: TextStyle(color: textSecondary, fontSize: 11, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: GoogleFonts.outfit(color: textPrimary, fontSize: 20, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Icon(
                trendUp ? Icons.trending_up : Icons.trending_down,
                color: trendUp ? Colors.green : Colors.red,
                size: 12,
              ),
              const SizedBox(width: 4),
              Text(
                '$percentage vs last week',
                style: TextStyle(color: trendUp ? Colors.green : Colors.red, fontSize: 9, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(BuildContext context, {required String title, required String actionText, required VoidCallback onTap}) {
    final textPrimary = Theme.of(context).brightness == Brightness.dark ? Colors.white : AppTheme.textPrimaryLight;
    return Padding(
      padding: const EdgeInsets.only(top: 8.0, bottom: 12.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            title,
            style: GoogleFonts.outfit(fontWeight: FontWeight.bold, fontSize: 16, color: textPrimary),
          ),
          TextButton(
            onPressed: onTap,
            child: Text(
              actionText,
              style: const TextStyle(color: Colors.blueAccent, fontSize: 12, fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmailItem({
    required String logoUrl,
    required String name,
    required String subject,
    required String time,
    required String priority,
    required Color priorityColor,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12.0),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: Colors.black.withOpacity(0.04)),
              image: DecorationImage(
                image: NetworkImage(logoUrl),
                fit: BoxFit.contain,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      name,
                      style: TextStyle(color: textPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                    Text(
                      time,
                      style: TextStyle(color: textSecondary, fontSize: 11),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        subject,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: textSecondary, fontSize: 12),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: priorityColor.withOpacity(0.08),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(color: priorityColor.withOpacity(0.15)),
                      ),
                      child: Text(
                        priority,
                        style: TextStyle(color: priorityColor, fontSize: 9, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildWhatsAppItem({
    required String avatarUrl,
    required String name,
    required String message,
    required String time,
    required int unreadCount,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12.0),
      child: Row(
        children: [
          CircleAvatar(
            radius: 16,
            backgroundImage: NetworkImage(avatarUrl),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      name,
                      style: TextStyle(color: textPrimary, fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                    Text(
                      time,
                      style: TextStyle(color: textSecondary, fontSize: 11),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text(
                        message,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: textSecondary, fontSize: 12),
                      ),
                    ),
                    if (unreadCount > 0)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: const BoxDecoration(
                          color: Colors.greenAccent,
                          shape: BoxShape.circle,
                        ),
                        child: Text(
                          unreadCount.toString(),
                          style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNavigateFooter(BuildContext context, {required String label, required VoidCallback onTap}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Column(
      children: [
        Divider(color: isDark ? AppTheme.slateBorder : Colors.black.withOpacity(0.04), height: 20),
        GestureDetector(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 4.0),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  label,
                  style: const TextStyle(color: Colors.blueAccent, fontWeight: FontWeight.bold, fontSize: 13),
                ),
                const Icon(Icons.arrow_forward, color: Colors.blueAccent, size: 16),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildTabButton(String tabName) {
    final isSelected = _activeAnalyticsTab == tabName;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return GestureDetector(
      onTap: () {
        setState(() {
          _activeAnalyticsTab = tabName;
        });
      },
      child: Container(
        margin: const EdgeInsets.only(right: 6),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: isSelected ? Colors.blueAccent.withOpacity(0.08) : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          tabName,
          style: TextStyle(
            color: isSelected ? Colors.blueAccent : (isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight),
            fontSize: 12,
            fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
          ),
        ),
      ),
    );
  }

  Widget _buildActivityItem({
    required IconData icon,
    required Color iconBgColor,
    required Color iconColor,
    required String title,
    required String time,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12.0),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: iconBgColor,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: iconColor, size: 16),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              title,
              style: TextStyle(color: textPrimary, fontSize: 13, fontWeight: FontWeight.w500),
            ),
          ),
          Text(
            time,
            style: TextStyle(color: textSecondary, fontSize: 11),
          ),
        ],
      ),
    );
  }

  Widget _buildQuickActionItem(BuildContext context, IconData icon, String label) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardBg = isDark ? AppTheme.slateCard : Colors.white;
    final cardBorder = isDark ? AppTheme.slateBorder : Colors.black.withOpacity(0.04);
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Column(
      children: [
        Container(
          width: 54,
          height: 54,
          decoration: BoxDecoration(
            color: cardBg,
            border: Border.all(color: cardBorder),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Icon(icon, color: Colors.blueAccent, size: 22),
        ),
        const SizedBox(height: 6),
        SizedBox(
          width: 60,
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(color: textSecondary, fontSize: 9, height: 1.2),
          ),
        ),
      ],
    );
  }
}

class SplineChartPainter extends CustomPainter {
  final bool isDark;
  final List<double> values;
  final String tooltipText;

  SplineChartPainter({
    required this.isDark,
    required this.values,
    required this.tooltipText,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.indigoAccent.withOpacity(0.15)
      ..style = PaintingStyle.fill;

    final linePaint = Paint()
      ..color = Colors.indigoAccent
      ..strokeWidth = 2.5
      ..style = PaintingStyle.stroke;

    final dotPaint = Paint()
      ..color = Colors.indigoAccent
      ..style = PaintingStyle.fill;

    final whiteDotPaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.fill;

    final gridPaint = Paint()
      ..color = isDark ? Colors.white.withOpacity(0.05) : Colors.black.withOpacity(0.03)
      ..strokeWidth = 1.0;

    final textPainter = TextPainter(
      textDirection: TextDirection.ltr,
    );

    // Draw horizontal grid lines
    for (int i = 0; i <= 4; i++) {
      double y = size.height * (i / 4);
      canvas.drawLine(Offset(0, y), Offset(size.width, y), gridPaint);
      
      // Draw grid labels
      String label = '';
      if (i == 0) label = '2K';
      if (i == 1) label = '1.5K';
      if (i == 2) label = '1K';
      if (i == 3) label = '500';
      if (i == 4) label = '0';
      
      textPainter.text = TextSpan(
        text: label,
        style: TextStyle(color: isDark ? Colors.white.withOpacity(0.3) : Colors.black.withOpacity(0.3), fontSize: 8),
      );
      textPainter.layout();
      textPainter.paint(canvas, Offset(-24, y - 6));
    }

    final points = [
      Offset(size.width * 0.05, size.height * values[0]),
      Offset(size.width * 0.20, size.height * values[1]),
      Offset(size.width * 0.35, size.height * values[2]),
      Offset(size.width * 0.50, size.height * values[3]), // Thursday (Active Dot)
      Offset(size.width * 0.65, size.height * values[4]),
      Offset(size.width * 0.80, size.height * values[5]),
      Offset(size.width * 0.95, size.height * values[6]),
    ];

    // Spline curve construction
    final path = Path();
    path.moveTo(points[0].dx, points[0].dy);

    for (int i = 0; i < points.length - 1; i++) {
      final p0 = points[i];
      final p1 = points[i + 1];
      final controlPoint1 = Offset(p0.dx + (p1.dx - p0.dx) / 2, p0.dy);
      final controlPoint2 = Offset(p0.dx + (p1.dx - p0.dx) / 2, p1.dy);
      path.cubicTo(controlPoint1.dx, controlPoint1.dy, controlPoint2.dx, controlPoint2.dy, p1.dx, p1.dy);
    }

    // Fill area under spline
    final fillPath = Path.from(path);
    fillPath.lineTo(points.last.dx, size.height);
    fillPath.lineTo(points.first.dx, size.height);
    fillPath.close();

    final fillGradient = LinearGradient(
      colors: [Colors.indigoAccent.withOpacity(0.25), Colors.indigoAccent.withOpacity(0.01)],
      begin: Alignment.topCenter,
      end: Alignment.bottomCenter,
    );
    paint.shader = fillGradient.createShader(Rect.fromLTWH(0, 0, size.width, size.height));
    canvas.drawPath(fillPath, paint);

    // Draw spline line
    canvas.drawPath(path, linePaint);

    // Draw labels at the bottom (Mon, Tue, Wed, Thu, Fri, Sat, Sun)
    final days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    for (int i = 0; i < points.length; i++) {
      textPainter.text = TextSpan(
        text: days[i],
        style: TextStyle(
          color: i == 3 
              ? Colors.indigoAccent 
              : (isDark ? Colors.white.withOpacity(0.4) : Colors.black.withOpacity(0.4)),
          fontWeight: i == 3 ? FontWeight.bold : FontWeight.normal,
          fontSize: 9,
        ),
      );
      textPainter.layout();
      textPainter.paint(canvas, Offset(points[i].dx - 10, size.height + 6));
    }

    // Draw Thursday marker line
    final markerPaint = Paint()
      ..color = Colors.indigoAccent.withOpacity(0.3)
      ..strokeWidth = 1.0;
    canvas.drawLine(Offset(points[3].dx, points[3].dy), Offset(points[3].dx, size.height), markerPaint);

    // Draw Active Marker Dot
    canvas.drawCircle(points[3], 6, dotPaint);
    canvas.drawCircle(points[3], 3, whiteDotPaint);

    // Draw Tooltip above active dot
    final tooltipPaint = Paint()
      ..color = Colors.indigoAccent
      ..style = PaintingStyle.fill;
    final rrect = RRect.fromRectAndRadius(
      Rect.fromCenter(center: Offset(points[3].dx, points[3].dy - 28), width: 48, height: 18),
      const Radius.circular(6),
    );
    canvas.drawRRect(rrect, tooltipPaint);

    // Tooltip text
    textPainter.text = TextSpan(
      text: tooltipText,
      style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
    );
    textPainter.layout();
    textPainter.paint(canvas, Offset(points[3].dx - (textPainter.width / 2), points[3].dy - 37));
  }

  @override
  bool shouldRepaint(covariant SplineChartPainter oldDelegate) {
    return oldDelegate.isDark != isDark || oldDelegate.tooltipText != tooltipText;
  }
}
