import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shimmer/shimmer.dart';
import '../../core/providers/providers.dart';
import '../../core/theme/theme.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardAsync = ref.watch(mobileDashboardProvider);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.bgSlate,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(mobileDashboardProvider);
          },
          child: dashboardAsync.when(
            data: (data) => _buildDashboardContent(context, ref, data),
            loading: () => _buildShimmerLoading(context),
            error: (err, stack) => _buildErrorState(context, ref, err.toString()),
          ),
        ),
      ),
    );
  }

  Widget _buildDashboardContent(BuildContext context, WidgetRef ref, Map<String, dynamic> data) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final user = data['user'] as Map<String, dynamic>? ?? {};
    final userName = user['name'] ?? 'Alex';

    final counts = data['counts'] as Map<String, dynamic>? ?? {};
    final tasksCount = counts['today_tasks'] ?? 3;
    final emailsCount = counts['unread_emails'] ?? 5;
    final waCount = counts['unread_whatsapp'] ?? 2;

    final priority = data['priority'] as Map<String, dynamic>? ?? {};
    final highPriorityTasks = (priority['high_priority_tasks'] as List<dynamic>?) ?? [];
    final importantEmails = (priority['important_emails'] as List<dynamic>?) ?? [];
    final whatsappAttention = (priority['whatsapp_attention'] as List<dynamic>?) ?? [];

    final todayTasks = (data['today_tasks'] as List<dynamic>?) ?? [];

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header Row: Greeting & Avatar (Mockup 4)
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Good morning,',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w400,
                      color: isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    userName,
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                    ),
                  ),
                  const SizedBox(height: 2),
                  const Text(
                    'Thu, Sep 12, 2024',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                      color: AppTheme.textSecondaryLight,
                    ),
                  ),
                ],
              ),
              // Avatar
              CircleAvatar(
                radius: 22,
                backgroundColor: AppTheme.brandBlue.withValues(alpha: 0.15),
                child: Text(
                  userName[0].toUpperCase(),
                  style: const TextStyle(
                    color: AppTheme.brandBlue,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // 3 Stat Cards Row (Tasks, Emails, WhatsApp)
          Row(
            children: [
              Expanded(
                child: _buildStatCard(
                  context,
                  count: '$tasksCount',
                  label: 'Tasks',
                  bgColor: isDark ? const Color(0xFF1E293B) : const Color(0xFFEFF6FF),
                  textColor: AppTheme.brandBlue,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildStatCard(
                  context,
                  count: '$emailsCount',
                  label: 'Emails',
                  bgColor: isDark ? const Color(0xFF1E293B) : const Color(0xFFEFF6FF),
                  textColor: AppTheme.brandBlue,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildStatCard(
                  context,
                  count: '$waCount',
                  label: 'WhatsApp',
                  bgColor: isDark ? const Color(0xFF1E293B) : const Color(0xFFECFDF5),
                  textColor: AppTheme.greenWhatsApp,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),

          // Priority Section
          _buildSectionHeader(
            context,
            title: 'Priority',
            onSeeAll: () => context.push('/tasks'),
          ),
          const SizedBox(height: 12),
          _buildPriorityItem(
            context,
            icon: Icons.outlined_flag_rounded,
            iconColor: AppTheme.redPriority,
            title: 'Complete project proposal',
            subtitle: 'Today, 11:00 AM',
          ),
          _buildPriorityItem(
            context,
            icon: Icons.outlined_flag_rounded,
            iconColor: AppTheme.yellowPriority,
            title: 'Client meeting follow-up',
            subtitle: 'Today, 3:00 PM',
          ),
          _buildPriorityItem(
            context,
            icon: Icons.mail_outline_rounded,
            iconColor: AppTheme.brandBlue,
            title: 'Reply to John\'s email',
            subtitle: 'Today',
          ),

          const SizedBox(height: 24),

          // Today's Tasks Section
          _buildSectionHeader(
            context,
            title: 'Today\'s Tasks',
            onSeeAll: () => context.push('/tasks'),
          ),
          const SizedBox(height: 12),
          if (todayTasks.isNotEmpty)
            ...todayTasks.take(2).map((t) => _buildTaskCheckboxTile(context, ref, t))
          else ...[
            _buildTaskCheckboxTile(
              context,
              ref,
              {'id': 1, 'title': 'Review marketing plan', 'due_date': 'Today', 'due_time': '10:00 AM', 'status': 'pending'},
            ),
            _buildTaskCheckboxTile(
              context,
              ref,
              {'id': 2, 'title': 'Prepare client presentation', 'due_date': 'Today', 'due_time': '2:00 PM', 'status': 'pending'},
            ),
          ],

          const SizedBox(height: 24),

          // Important Emails Section (Mockup 5)
          _buildSectionHeader(
            context,
            title: 'Important Emails',
            onSeeAll: () => context.go('/emails'),
          ),
          const SizedBox(height: 12),
          if (importantEmails.isNotEmpty)
            ...importantEmails.take(3).map((e) => _buildEmailCard(context, e))
          else ...[
            _buildEmailCard(context, {
              'sender_name': 'Sarah Johnson',
              'subject': 'Re: Project Update',
              'body_text': 'Please find the updated contract...',
              'received_date': '10:24 AM',
              'is_read': 0,
              'icon_color': Colors.blue,
            }),
            _buildEmailCard(context, {
              'sender_name': 'David Miller',
              'subject': 'Contract Review',
              'body_text': 'Please find the updated contract...',
              'received_date': '9:15 AM',
              'is_read': 0,
              'icon_color': Colors.redAccent,
            }),
            _buildEmailCard(context, {
              'sender_name': 'noreply@stripe.com',
              'subject': 'Payment successful',
              'body_text': 'Your payment of \$199 has been...',
              'received_date': 'Yesterday',
              'is_read': 1,
              'icon_color': Colors.purple,
            }),
          ],

          const SizedBox(height: 24),

          // WhatsApp Requiring Attention Section (Mockup 5)
          _buildSectionHeader(
            context,
            title: 'WhatsApp Requiring Attention',
            onSeeAll: () => context.go('/whatsapp'),
          ),
          const SizedBox(height: 12),
          if (whatsappAttention.isNotEmpty)
            ...whatsappAttention.take(3).map((w) => _buildWhatsAppCard(context, w))
          else ...[
            _buildWhatsAppCard(context, {
              'name': 'Rahul Mehta',
              'last_message': 'Can you share the latest report?',
              'last_message_time': '11:02 AM',
              'unread_count': 2,
            }),
            _buildWhatsAppCard(context, {
              'name': 'ABC Coaching',
              'last_message': 'We need the final files by today.',
              'last_message_time': '9:40 AM',
              'unread_count': 1,
            }),
            _buildWhatsAppCard(context, {
              'name': 'Priya Sharma',
              'last_message': 'Thanks! I\'ll check and get back.',
              'last_message_time': 'Yesterday',
              'unread_count': 0,
            }),
          ],

          const SizedBox(height: 28),
        ],
      ),
    );
  }

  Widget _buildStatCard(
    BuildContext context, {
    required String count,
    required String label,
    required Color bgColor,
    required Color textColor,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Text(
            count,
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w800,
              color: textColor,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: textColor.withValues(alpha: 0.8),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(
    BuildContext context, {
    required String title,
    required VoidCallback onSeeAll,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          title,
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w700,
            color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
          ),
        ),
        GestureDetector(
          onTap: onSeeAll,
          child: const Text(
            'See all',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppTheme.brandBlue,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildPriorityItem(
    BuildContext context, {
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: Row(
        children: [
          Icon(icon, color: iconColor, size: 20),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
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
    );
  }

  Widget _buildTaskCheckboxTile(BuildContext context, WidgetRef ref, Map<String, dynamic> task) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isCompleted = task['status'] == 'completed';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: Row(
        children: [
          Container(
            width: 22,
            height: 22,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(6),
              border: Border.all(
                color: isCompleted ? AppTheme.brandBlue : const Color(0xFFCBD5E1),
                width: 1.8,
              ),
              color: isCompleted ? AppTheme.brandBlue : Colors.transparent,
            ),
            child: isCompleted ? const Icon(Icons.check_rounded, size: 14, color: Colors.white) : null,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  task['title'] ?? 'Task',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                    decoration: isCompleted ? TextDecoration.lineThrough : null,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${task['due_date'] ?? 'Today'}${task['due_time'] != null ? ', ${task['due_time']}' : ''}',
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
    );
  }

  Widget _buildEmailCard(BuildContext context, Map<String, dynamic> email) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final senderName = email['sender_name'] ?? 'Sender';
    final subject = email['subject'] ?? 'Subject';
    final preview = email['body_text'] ?? '';
    final time = email['received_date'] ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: (email['icon_color'] as Color? ?? AppTheme.brandBlue).withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              Icons.mail_rounded,
              color: email['icon_color'] as Color? ?? AppTheme.brandBlue,
              size: 20,
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
                      senderName,
                      style: TextStyle(
                        fontSize: 14,
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
                const SizedBox(height: 2),
                Text(
                  subject,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                  ),
                ),
                if (preview.isNotEmpty) ...[
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
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildWhatsAppCard(BuildContext context, Map<String, dynamic> wa) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final name = wa['name'] ?? 'Contact';
    final msg = wa['last_message'] ?? '';
    final time = wa['last_message_time'] ?? '';
    final unread = wa['unread_count'] ?? 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 20,
            backgroundColor: AppTheme.greenWhatsApp.withValues(alpha: 0.15),
            child: Text(
              name[0].toUpperCase(),
              style: const TextStyle(
                color: AppTheme.greenWhatsApp,
                fontWeight: FontWeight.bold,
                fontSize: 15,
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
                        fontSize: 14,
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
                const SizedBox(height: 2),
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
    );
  }

  Widget _buildShimmerLoading(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Shimmer.fromColors(
      baseColor: isDark ? AppTheme.slateCard : Colors.grey[300]!,
      highlightColor: isDark ? AppTheme.slateBorder : Colors.grey[100]!,
      child: ListView.builder(
        padding: const EdgeInsets.all(20),
        itemCount: 5,
        itemBuilder: (_, _) => Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: Container(
            height: 70,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildErrorState(BuildContext context, WidgetRef ref, String error) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.cloud_off_rounded, size: 48, color: AppTheme.redPriority),
            const SizedBox(height: 16),
            const Text(
              'Couldn\'t load dashboard',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Please check your internet connection and try again.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[600]),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: () => ref.invalidate(mobileDashboardProvider),
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
