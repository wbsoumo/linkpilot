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
    final userName = user['name'] ?? 'Alex Thompson';

    final counts = data['counts'] as Map<String, dynamic>? ?? {};
    final tasksCount = counts['today_tasks'] ?? 3;
    final emailsCount = counts['unread_emails'] ?? 5;
    final waCount = counts['unread_whatsapp'] ?? 2;

    final todayTasks = (data['today_tasks'] as List<dynamic>?) ?? [];

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top Navigation Bar (Logo, Title, Notification Bell with badge & Profile Avatar)
          Row(
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
                  // Notification Bell with badge count '3'
                  Stack(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF1F5F9),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.notifications_none_rounded, color: Color(0xFF0F172A), size: 22),
                      ),
                      Positioned(
                        right: 2,
                        top: 2,
                        child: Container(
                          padding: const EdgeInsets.all(4),
                          decoration: const BoxDecoration(
                            color: Color(0xFFEF4444),
                            shape: BoxShape.circle,
                          ),
                          child: const Text(
                            '3',
                            style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(width: 12),
                  // User Avatar
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: const Color(0xFFE2E8F0),
                    child: const Text(
                      'AT',
                      style: TextStyle(color: Color(0xFF0F172A), fontSize: 13, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 20),

          // User Greeting & Stay Focused Widget Header
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Good morning,',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                      color: Color(0xFF64748B),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    userName,
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      color: isDark ? AppTheme.textPrimaryDark : const Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 2),
                  const Text(
                    'Thu, Sep 12, 2024',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w500,
                      color: Color(0xFF94A3B8),
                    ),
                  ),
                ],
              ),

              // Stay Focused Banner Card
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.wb_sunny_outlined, color: Color(0xFFF59E0B), size: 20),
                    const SizedBox(width: 8),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: const [
                        Text('Stay focused.', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF475569))),
                        Text('Make progress.', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF475569))),
                      ],
                    ),
                    const SizedBox(width: 6),
                    const Icon(Icons.chevron_right_rounded, color: Color(0xFF94A3B8), size: 18),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // 3 Stat Cards (Tasks, Emails, WhatsApp with Icons & Arrows)
          Row(
            children: [
              Expanded(
                child: _buildStatCardMockup(
                  icon: Icons.check_box_rounded,
                  iconColor: Colors.white,
                  iconBgColor: const Color(0xFF005BF7),
                  count: '$tasksCount',
                  label: 'Tasks',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildStatCardMockup(
                  icon: Icons.mail_outline_rounded,
                  iconColor: const Color(0xFF8B5CF6),
                  iconBgColor: const Color(0xFFF3E8FF),
                  count: '$emailsCount',
                  label: 'Emails',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildStatCardMockup(
                  icon: Icons.chat_bubble_outline_rounded,
                  iconColor: const Color(0xFF22C55E),
                  iconBgColor: const Color(0xFFDCFCE7),
                  count: '$waCount',
                  label: 'WhatsApp',
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
          Container(
            padding: const EdgeInsets.symmetric(vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFF1F5F9)),
            ),
            child: Column(
              children: [
                _buildPriorityRow(
                  icon: Icons.flag_rounded,
                  iconColor: const Color(0xFFEF4444),
                  title: 'Complete project proposal',
                  subtitle: 'Send the final version to client',
                  time: 'Today, 11:00 AM',
                  tagText: 'High',
                  tagBg: const Color(0xFFFEE2E2),
                  tagColor: const Color(0xFFEF4444),
                ),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                _buildPriorityRow(
                  icon: Icons.mail_rounded,
                  iconColor: const Color(0xFFF59E0B),
                  title: 'Re: Contract Review',
                  subtitle: 'David Miller • Please find the updated contract...',
                  time: '9:15 AM',
                  tagText: 'Important',
                  tagBg: const Color(0xFFFEF3C7),
                  tagColor: const Color(0xFFD97706),
                ),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                _buildPriorityRow(
                  icon: Icons.chat_bubble_rounded,
                  iconColor: const Color(0xFF22C55E),
                  title: 'Rahul Mehta',
                  subtitle: 'Can you share the latest report?',
                  time: '11:02 AM',
                  tagText: 'Needs Reply',
                  tagBg: const Color(0xFFDBEAFE),
                  tagColor: const Color(0xFF2563EB),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // Today's Tasks Section
          _buildSectionHeader(
            context,
            title: 'Today\'s Tasks',
            onSeeAll: () => context.push('/tasks'),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFF1F5F9)),
            ),
            child: Column(
              children: [
                _buildTaskRowMockup(
                  title: 'Review marketing plan',
                  time: 'Today, 10:00 AM',
                  flagColor: const Color(0xFFEF4444),
                  isChecked: false,
                ),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                _buildTaskRowMockup(
                  title: 'Prepare client presentation',
                  time: 'Today, 2:00 PM',
                  flagColor: const Color(0xFFF59E0B),
                  isChecked: false,
                ),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                _buildTaskRowMockup(
                  title: 'Update website content',
                  time: 'Today, 4:00 PM',
                  flagColor: const Color(0xFFCBD5E1),
                  isChecked: true,
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // Recent Emails Section
          _buildSectionHeader(
            context,
            title: 'Recent Emails',
            onSeeAll: () => context.go('/emails'),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFF1F5F9)),
            ),
            child: Column(
              children: [
                _buildEmailRowMockup(
                  initials: 'SJ',
                  bgColor: const Color(0xFFDBEAFE),
                  textColor: const Color(0xFF2563EB),
                  sender: 'Sarah Johnson',
                  subject: 'Re: Project Update',
                  snippet: 'Let\'s schedule a call to discuss the next steps...',
                  time: '10:24 AM',
                  showBlueDot: true,
                ),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                _buildEmailRowMockup(
                  initials: 'DM',
                  bgColor: const Color(0xFFF3E8FF),
                  textColor: const Color(0xFF9333EA),
                  sender: 'David Miller',
                  subject: 'Contract Review',
                  snippet: 'Please find the updated contract attached...',
                  time: '9:15 AM',
                  showBlueDot: true,
                ),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                _buildEmailRowMockup(
                  initials: 'MC',
                  bgColor: const Color(0xFFCCFBF1),
                  textColor: const Color(0xFF0D9488),
                  sender: 'Michael Chen',
                  subject: 'Meeting Tomorrow',
                  snippet: 'Just confirming our meeting for tomorrow...',
                  time: '8:32 AM',
                  showBlueDot: false,
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // Recent WhatsApp Section
          _buildSectionHeader(
            context,
            title: 'Recent WhatsApp',
            onSeeAll: () => context.go('/whatsapp'),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFF1F5F9)),
            ),
            child: Column(
              children: [
                _buildWhatsAppRowMockup(
                  initials: 'RM',
                  bgColor: const Color(0xFF166534),
                  textColor: Colors.white,
                  name: 'Rahul Mehta',
                  snippet: 'Can you share the latest report?',
                  time: '11:02 AM',
                  badgeCount: '2',
                ),
                const Divider(height: 1, color: Color(0xFFF1F5F9)),
                _buildWhatsAppRowMockup(
                  initials: 'AC',
                  bgColor: const Color(0xFF5EEAD4),
                  textColor: const Color(0xFF0F766E),
                  name: 'ABC Coaching',
                  snippet: 'We need the final files by today.',
                  time: '9:40 AM',
                  badgeCount: '1',
                ),
              ],
            ),
          ),

          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildStatCardMockup({
    required IconData icon,
    required Color iconColor,
    required Color iconBgColor,
    required String count,
    required String label,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFF1F5F9)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: iconBgColor,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: iconColor, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  count,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF0F172A),
                  ),
                ),
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                    color: Color(0xFF64748B),
                  ),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: Color(0xFFCBD5E1), size: 16),
        ],
      ),
    );
  }

  Widget _buildPriorityRow({
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
    required String time,
    required String tagText,
    required Color tagBg,
    required Color tagColor,
  }) {
    return Padding(
      padding: const EdgeInsets.all(14.0),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: iconColor, size: 18),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                time,
                style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8), fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: tagBg,
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  tagText,
                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: tagColor),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTaskRowMockup({
    required String title,
    required String time,
    required Color flagColor,
    required bool isChecked,
  }) {
    return Padding(
      padding: const EdgeInsets.all(14.0),
      child: Row(
        children: [
          Container(
            width: 20,
            height: 20,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(6),
              border: Border.all(
                color: isChecked ? const Color(0xFF005BF7) : const Color(0xFFCBD5E1),
                width: 1.5,
              ),
              color: isChecked ? const Color(0xFF005BF7) : Colors.transparent,
            ),
            child: isChecked ? const Icon(Icons.check_rounded, size: 14, color: Colors.white) : null,
          ),
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
                    color: isChecked ? const Color(0xFF94A3B8) : const Color(0xFF0F172A),
                    decoration: isChecked ? TextDecoration.lineThrough : null,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  time,
                  style: const TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                ),
              ],
            ),
          ),
          Icon(Icons.flag_rounded, color: flagColor, size: 18),
        ],
      ),
    );
  }

  Widget _buildEmailRowMockup({
    required String initials,
    required Color bgColor,
    required Color textColor,
    required String sender,
    required String subject,
    required String snippet,
    required String time,
    required bool showBlueDot,
  }) {
    return Padding(
      padding: const EdgeInsets.all(14.0),
      child: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: bgColor,
            child: Text(
              initials,
              style: TextStyle(color: textColor, fontWeight: FontWeight.bold, fontSize: 13),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  sender,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                Text(
                  subject,
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF475569)),
                ),
                Text(
                  snippet,
                  style: const TextStyle(fontSize: 11.5, color: Color(0xFF94A3B8)),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          Row(
            children: [
              Text(
                time,
                style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
              ),
              if (showBlueDot) ...[
                const SizedBox(width: 6),
                Container(
                  width: 7,
                  height: 7,
                  decoration: const BoxDecoration(
                    color: Color(0xFF005BF7),
                    shape: BoxShape.circle,
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildWhatsAppRowMockup({
    required String initials,
    required Color bgColor,
    required Color textColor,
    required String name,
    required String snippet,
    required String time,
    required String badgeCount,
  }) {
    return Padding(
      padding: const EdgeInsets.all(14.0),
      child: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: bgColor,
            child: Text(
              initials,
              style: TextStyle(color: textColor, fontWeight: FontWeight.bold, fontSize: 13),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                const SizedBox(height: 2),
                Text(
                  snippet,
                  style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                time,
                style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
              ),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.all(5),
                decoration: const BoxDecoration(
                  color: Color(0xFF005BF7),
                  shape: BoxShape.circle,
                ),
                child: Text(
                  badgeCount,
                  style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
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
