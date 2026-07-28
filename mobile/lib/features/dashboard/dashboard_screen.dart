import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
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
  bool _autopilotEnabled = true;

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authStateProvider);
    final user = authState.user;

    final dashboardAsync = ref.watch(dashboardDataProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(
          'LinkPilot Hub',
          style: GoogleFonts.outfit(
            fontWeight: FontWeight.bold,
            color: Theme.of(context).brightness == Brightness.dark ? Colors.white : AppTheme.textPrimaryLight,
          ),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        actions: [
          // Auto-Reply status badge
          GestureDetector(
            onTap: () {
              setState(() {
                _autopilotEnabled = !_autopilotEnabled;
              });
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(
                    _autopilotEnabled ? 'Autopilot Autoreply Activated' : 'Autopilot Autoreply Paused',
                  ),
                ),
              );
            },
            child: Container(
              margin: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              decoration: BoxDecoration(
                color: _autopilotEnabled
                    ? Colors.green.withOpacity(0.15)
                    : Colors.red.withOpacity(0.15),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: _autopilotEnabled ? Colors.greenAccent : Colors.redAccent,
                  width: 1,
                ),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: _autopilotEnabled ? Colors.greenAccent : Colors.redAccent,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    _autopilotEnabled ? 'Autopilot Live' : 'Autopilot Paused',
                    style: TextStyle(
                      color: _autopilotEnabled ? Colors.greenAccent : Colors.redAccent,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
      body: Container(
        decoration: BoxDecoration(
          gradient: AppTheme.bgGradient(context),
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Welcome header
              Text(
                'Hello, ${user?['name'] ?? 'Partner'}',
                style: GoogleFonts.outfit(
                  fontSize: 24,
                  fontWeight: FontWeight.bold,
                  color: Theme.of(context).brightness == Brightness.dark ? Colors.white : AppTheme.textPrimaryLight,
                ),
              ),
              Text(
                'Here is your outreach activity outline for today.',
                style: TextStyle(
                  color: Theme.of(context).brightness == Brightness.dark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight,
                ),
              ),
              const SizedBox(height: 24),

              // Overview Cards
              dashboardAsync.when(
                data: (data) {
                  final stats = data['statistics'] ?? {};
                  final totalRequests = stats['total_requests'] ?? 0;
                  final emailsSent = stats['emails_sent'] ?? 0;
                  final whatsappGenerated = stats['whatsapp_generated'] ?? 0;
                  final emailsReceived = stats['emails_received'] ?? 0;

                  return GridView.count(
                    crossAxisCount: 2,
                    crossAxisSpacing: 16,
                    mainAxisSpacing: 16,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      _buildMetricCard(
                        icon: Icons.email_outlined,
                        title: 'Received Emails',
                        value: '$emailsReceived Emails',
                        subtitle: 'From integrated inbox',
                        color: AppTheme.primaryPurple,
                      ),
                      _buildMetricCard(
                        icon: Icons.forum_outlined,
                        title: 'WhatsApp Chats',
                        value: '$whatsappGenerated Messages',
                        subtitle: 'Processed by Autopilot',
                        color: AppTheme.accentTeal,
                      ),
                      _buildMetricCard(
                        icon: Icons.payment,
                        title: 'Total Outbound',
                        value: '$emailsSent Sent',
                        subtitle: 'Sent successfully',
                        color: AppTheme.priorityOrange,
                      ),
                      _buildMetricCard(
                        icon: Icons.insights,
                        title: 'AI Generations',
                        value: '$totalRequests Runs',
                        subtitle: 'AI request executions',
                        color: Colors.greenAccent,
                      ),
                    ],
                  );
                },
                loading: () => const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24.0),
                    child: CircularProgressIndicator(color: AppTheme.primaryPurple),
                  ),
                ),
                error: (err, stack) => GridView.count(
                  crossAxisCount: 2,
                  crossAxisSpacing: 16,
                  mainAxisSpacing: 16,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  children: [
                    _buildMetricCard(
                      icon: Icons.email_outlined,
                      title: 'Priority Emails',
                      value: '5 Need Reply',
                      subtitle: '12 unread in threads',
                      color: AppTheme.primaryPurple,
                    ),
                    _buildMetricCard(
                      icon: Icons.forum_outlined,
                      title: 'WhatsApp chats',
                      value: '8 Connected',
                      subtitle: '2 auto-replied recently',
                      color: AppTheme.accentTeal,
                    ),
                    _buildMetricCard(
                      icon: Icons.payment,
                      title: 'AI Credits',
                      value: '₹340.50',
                      subtitle: '2,270 messages left',
                      color: AppTheme.priorityOrange,
                    ),
                    _buildMetricCard(
                      icon: Icons.insights,
                      title: 'Open Rate',
                      value: '64.2%',
                      subtitle: '+4.3% from last week',
                      color: Colors.greenAccent,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 28),

              // Upcoming meetings section
              Text(
                'Meetings Today',
                style: GoogleFonts.outfit(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: Theme.of(context).brightness == Brightness.dark ? Colors.white : AppTheme.textPrimaryLight,
                ),
              ),
              const SizedBox(height: 12),
              ref.watch(crmMeetingsProvider).when(
                data: (meetings) {
                  if (meetings.isEmpty) {
                    final textSec = Theme.of(context).brightness == Brightness.dark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8.0),
                      child: Text('No meetings scheduled for today.', style: TextStyle(color: textSec, fontSize: 13)),
                    );
                  }
                  return Column(
                    children: meetings.map((meet) {
                      return _buildMeetingItem(
                        time: meet['meeting_time'] ?? meet['time'] ?? 'All Day',
                        title: meet['title'] ?? 'CRM Event',
                        attendees: meet['attendees'] ?? '',
                        platform: meet['location'] ?? meet['platform'] ?? 'Video Call',
                      );
                    }).toList(),
                  );
                },
                loading: () => const Center(child: CircularProgressIndicator(color: AppTheme.primaryPurple)),
                error: (err, stack) {
                  final textSec = Theme.of(context).brightness == Brightness.dark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;
                  return Text('Error loading meetings.', style: TextStyle(color: textSec));
                },
              ),
              const SizedBox(height: 28),

              // Today's checklist
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Active Actions Required',
                    style: GoogleFonts.outfit(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Theme.of(context).brightness == Brightness.dark ? Colors.white : AppTheme.textPrimaryLight,
                    ),
                  ),
                  TextButton(
                    onPressed: () {},
                    child: const Text('Add Task', style: TextStyle(color: AppTheme.secondaryPurple)),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              ref.watch(crmTasksProvider).when(
                data: (tasks) {
                  if (tasks.isEmpty) {
                    final textSec = Theme.of(context).brightness == Brightness.dark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8.0),
                      child: Text('All tasks completed!', style: TextStyle(color: textSec, fontSize: 13)),
                    );
                  }
                  return Column(
                    children: tasks.map((task) {
                      final isCompleted = task['status'] == 'Completed' || task['status'] == 'completed';
                      return _buildTaskItem(task['title'] ?? 'CRM Task', isCompleted);
                    }).toList(),
                  );
                },
                loading: () => const Center(child: CircularProgressIndicator(color: AppTheme.primaryPurple)),
                error: (err, stack) {
                  final textSec = Theme.of(context).brightness == Brightness.dark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;
                  return Text('Error loading tasks.', style: TextStyle(color: textSec));
                },
              ),
              const SizedBox(height: 40),
            ],
          ),
        ),
      ),
      floatingActionButton: const AiFloatingActionButton(),
      bottomNavigationBar: const LinkPilotBottomNav(currentIndex: 0),
    );
  }

  Widget _buildMetricCard({
    required IconData icon,
    required String title,
    required String value,
    required String subtitle,
    required Color color,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: AppTheme.glassBoxAdaptive(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Icon(icon, color: color, size: 28),
              Icon(Icons.arrow_forward_ios, color: textSecondary, size: 12),
            ],
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(color: textSecondary, fontSize: 13),
              ),
              const SizedBox(height: 4),
              Text(
                value,
                style: TextStyle(color: textPrimary, fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: TextStyle(color: textPrimary.withOpacity(0.5), fontSize: 11),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildMeetingItem({
    required String time,
    required String title,
    required String attendees,
    required String platform,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: AppTheme.glassBoxAdaptive(context),
      child: Row(
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                time,
                style: const TextStyle(
                  color: AppTheme.secondaryPurple,
                  fontWeight: FontWeight.bold,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 4),
              Text('Today', style: TextStyle(color: textSecondary, fontSize: 11)),
            ],
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(color: textPrimary, fontWeight: FontWeight.bold, fontSize: 15),
                ),
                const SizedBox(height: 2),
                Text(
                  'With $attendees | $platform',
                  style: TextStyle(color: textSecondary, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTaskItem(String text, bool checked) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary = isDark ? Colors.white : AppTheme.textPrimaryLight;
    final textSecondary = isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: AppTheme.glassBoxAdaptive(context),
      child: Row(
        children: [
          Icon(
            checked ? Icons.check_circle : Icons.radio_button_unchecked,
            color: checked ? Colors.greenAccent : textSecondary,
            size: 20,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: checked ? textSecondary : textPrimary,
                decoration: checked ? TextDecoration.lineThrough : null,
                fontSize: 14,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
