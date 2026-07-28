import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/bottom_nav.dart';
import '../../core/widgets/ai_floating_button.dart';
import '../../core/providers/providers.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  String _selectedTimeline = 'This Week';
  String _activeAnalyticsTab = 'Emails';

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authStateProvider);
    final user = authState.user;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;
    final cardBg = isDark ? AppTheme.slateCard : Colors.white;
    final cardBorder = isDark ? AppTheme.slateBorder : Colors.black.withOpacity(0.04);

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
            onPressed: () {},
          ),
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: Icon(Icons.notifications_none_outlined, color: textPrimary, size: 24),
                onPressed: () {},
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
                Container(
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
              ],
            ),
            const SizedBox(height: 20),

            // Horizontal Scrolling 4 Stats Cards
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _buildStatCard(
                    context: context,
                    icon: Icons.email_outlined,
                    iconColor: Colors.deepPurple,
                    bgColor: Colors.deepPurple.withOpacity(0.08),
                    title: 'Total Emails',
                    value: '1.248',
                    percentage: '12.5%',
                    trendUp: true,
                  ),
                  _buildStatCard(
                    context: context,
                    icon: Icons.send_rounded,
                    iconColor: Colors.teal,
                    bgColor: Colors.teal.withOpacity(0.08),
                    title: 'WhatsApp Chats',
                    value: '342',
                    percentage: '8.4%',
                    trendUp: true,
                  ),
                  _buildStatCard(
                    context: context,
                    icon: Icons.workspace_premium_outlined,
                    iconColor: Colors.amber[800]!,
                    bgColor: Colors.amber.withOpacity(0.08),
                    title: 'New Leads',
                    value: '86',
                    percentage: '15.2%',
                    trendUp: true,
                  ),
                  _buildStatCard(
                    context: context,
                    icon: Icons.monetization_on_outlined,
                    iconColor: Colors.blueAccent,
                    bgColor: Colors.blueAccent.withOpacity(0.08),
                    title: 'Deals Won',
                    value: '₹2.45L',
                    percentage: '18.7%',
                    trendUp: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // AI Insight Banner (Beta)
            Container(
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
                  child: Column(
                    children: [
                      _buildEmailItem(
                        logoUrl: 'https://img.logo.dev/gmail.com',
                        name: 'Alex Johnson',
                        subject: 'Proposal for LinkPilot Enterprise',
                        time: '10:24 AM',
                        priority: 'High',
                        priorityColor: Colors.deepPurple,
                      ),
                      _buildEmailItem(
                        logoUrl: 'https://img.logo.dev/microsoft.com',
                        name: 'Mark Thompson',
                        subject: 'Re: Integration Discussion',
                        time: '09:15 AM',
                        priority: 'Medium',
                        priorityColor: Colors.orange,
                      ),
                      _buildEmailItem(
                        logoUrl: 'https://img.logo.dev/gmail.com',
                        name: 'Sarah Williams',
                        subject: 'Meeting Request – Next Steps',
                        time: '08:45 AM',
                        priority: 'High',
                        priorityColor: Colors.deepPurple,
                      ),
                      _buildEmailItem(
                        logoUrl: 'https://img.logo.dev/yahoo.com',
                        name: 'David Miller',
                        subject: 'Budget Approval Needed',
                        time: 'Yesterday',
                        priority: 'Low',
                        priorityColor: Colors.blue,
                      ),
                      _buildEmailItem(
                        logoUrl: 'https://img.logo.dev/slack.com',
                        name: 'Lisa Anderson',
                        subject: 'Quarterly Review Updates',
                        time: 'Yesterday',
                        priority: 'Medium',
                        priorityColor: Colors.orange,
                      ),
                      _buildNavigateFooter(context, label: 'Go to Inbox', onTap: () => context.go('/emails')),
                    ],
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
                  child: Column(
                    children: [
                      _buildWhatsAppItem(
                        avatarUrl: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=100&q=80',
                        name: 'Rahul Sharma',
                        message: 'Thanks! Please send...',
                        time: '10:30 AM',
                        unreadCount: 2,
                      ),
                      _buildWhatsAppItem(
                        avatarUrl: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=100&q=80',
                        name: 'Priya Mehta',
                        message: 'Sure, I will check and...',
                        time: '09:48 AM',
                        unreadCount: 1,
                      ),
                      _buildWhatsAppItem(
                        avatarUrl: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=100&q=80',
                        name: 'Amit Verma',
                        message: 'Call me when free',
                        time: 'Yesterday',
                        unreadCount: 0,
                      ),
                      _buildWhatsAppItem(
                        avatarUrl: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&w=100&q=80',
                        name: 'Neha Kapoor',
                        message: 'We need this by Friday',
                        time: 'Yesterday',
                        unreadCount: 3,
                      ),
                      _buildWhatsAppItem(
                        avatarUrl: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=100&q=80',
                        name: 'Vikram Singh',
                        message: 'Perfect, thank you!',
                        time: 'Yesterday',
                        unreadCount: 0,
                      ),
                      _buildNavigateFooter(context, label: 'Open WhatsApp', onTap: () => context.go('/whatsapp')),
                    ],
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
                      painter: SplineChartPainter(isDark: isDark),
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
                _buildQuickActionItem(context, Icons.email_outlined, 'Compose Email'),
                _buildQuickActionItem(context, Icons.person_add_outlined, 'New Contact'),
                _buildQuickActionItem(context, Icons.monetization_on_outlined, 'Add Deal'),
                _buildQuickActionItem(context, Icons.calendar_today_outlined, 'Schedule Meeting'),
                _buildQuickActionItem(context, Icons.check_box_outlined, 'Create Task'),
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
            color: isSelected ? Colors.blueAccent : AppTheme.textSecondaryDark,
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

  SplineChartPainter({required this.isDark});

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
      Offset(size.width * 0.05, size.height * 0.8),
      Offset(size.width * 0.20, size.height * 0.5),
      Offset(size.width * 0.35, size.height * 0.6),
      Offset(size.width * 0.50, size.height * 0.4), // Thursday (Active Dot)
      Offset(size.width * 0.65, size.height * 0.7),
      Offset(size.width * 0.80, size.height * 0.5),
      Offset(size.width * 0.95, size.height * 0.75),
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

    // Draw Tooltip "1,248" above active dot
    final tooltipPaint = Paint()
      ..color = Colors.indigoAccent
      ..style = PaintingStyle.fill;
    final rrect = RRect.fromRectAndRadius(
      Rect.fromCenter(center: Offset(points[3].dx, points[3].dy - 28), width: 42, height: 18),
      const Radius.circular(6),
    );
    canvas.drawRRect(rrect, tooltipPaint);

    // Tooltip text
    textPainter.text = const TextSpan(
      text: '1,248',
      style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
    );
    textPainter.layout();
    textPainter.paint(canvas, Offset(points[3].dx - 12, points[3].dy - 37));
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
