import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/providers/providers.dart';
import '../../core/theme/theme.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  Future<void> _openWebsiteHandoff(BuildContext context, String path) async {
    final Uri url = Uri.parse('https://linkpilot.work$path');
    if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open Link Pilot website.')),
        );
      }
    }
  }

  void _confirmLogout(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Sign Out'),
        content: const Text('Are you sure you want to sign out of Link Pilot Mobile?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              await ref.read(authStateProvider.notifier).logout();
              if (context.mounted) {
                context.go('/welcome');
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.redPriority),
            child: const Text('Sign Out', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settingsAsync = ref.watch(mobileSettingsProvider);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.bgSlate,
      appBar: AppBar(
        title: const Text('Settings', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 24)),
      ),
      body: settingsAsync.when(
        data: (data) => _buildSettingsContent(context, ref, data, isDark),
        loading: () => _buildMockSettingsContent(context, ref, isDark),
        error: (_, __) => _buildMockSettingsContent(context, ref, isDark),
      ),
    );
  }

  Widget _buildMockSettingsContent(BuildContext context, WidgetRef ref, bool isDark) {
    return _buildSettingsContent(context, ref, {
      'user': {
        'name': 'Alex Thompson',
        'email': 'alex@company.com',
      },
      'integrations': {
        'gmail': {'connected': true},
        'whatsapp': {'connected': true},
      }
    }, isDark);
  }

  Widget _buildSettingsContent(
    BuildContext context,
    WidgetRef ref,
    Map<String, dynamic> data,
    bool isDark,
  ) {
    final user = data['user'] as Map<String, dynamic>? ?? {};
    final userName = user['name'] ?? 'Alex Thompson';
    final userEmail = user['email'] ?? 'alex@company.com';

    final integrations = data['integrations'] as Map<String, dynamic>? ?? {};
    final gmailConnected = integrations['gmail']?['connected'] as bool? ?? true;
    final whatsappConnected = integrations['whatsapp']?['connected'] as bool? ?? true;

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
      child: Column(
        children: [
          // Profile Header Card (Mockup 12)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 26,
                  backgroundColor: AppTheme.brandBlue.withValues(alpha: 0.15),
                  child: Text(
                    userName[0].toUpperCase(),
                    style: const TextStyle(
                      color: AppTheme.brandBlue,
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        userName,
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        userEmail,
                        style: const TextStyle(
                          fontSize: 13,
                          color: AppTheme.textSecondaryLight,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.arrow_forward_ios_rounded, size: 16, color: AppTheme.textSecondaryLight),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Menu Options List (Mockup 12)
          _buildSettingsMenuItem(
            context,
            icon: Icons.person_outline_rounded,
            title: 'Account',
            onTap: () {},
          ),
          _buildSettingsMenuItem(
            context,
            icon: Icons.notifications_none_rounded,
            title: 'Notifications',
            onTap: () {},
          ),

          // Connected Services Section
          _buildConnectedServicesTile(
            context,
            gmailConnected: gmailConnected,
            whatsappConnected: whatsappConnected,
            onTap: () => _openWebsiteHandoff(context, '/dashboard/setup.html'),
          ),

          _buildSettingsMenuItem(
            context,
            icon: Icons.security_rounded,
            title: 'Security',
            onTap: () {},
          ),
          _buildSettingsMenuItem(
            context,
            icon: Icons.help_outline_rounded,
            title: 'Help & Support',
            onTap: () {},
          ),
          _buildSettingsMenuItem(
            context,
            icon: Icons.open_in_new_rounded,
            title: 'Open Link Pilot Website',
            onTap: () => _openWebsiteHandoff(context, '/dashboard/index.html'),
          ),

          const SizedBox(height: 12),

          // Logout Button (Mockup 12)
          InkWell(
            onTap: () => _confirmLogout(context, ref),
            borderRadius: BorderRadius.circular(14),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
              ),
              child: const Row(
                children: [
                  Icon(Icons.logout_rounded, color: AppTheme.redPriority, size: 20),
                  SizedBox(width: 14),
                  Text(
                    'Logout',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.redPriority,
                    ),
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 28),
        ],
      ),
    );
  }

  Widget _buildSettingsMenuItem(
    BuildContext context, {
    required IconData icon,
    required String title,
    required VoidCallback onTap,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: ListTile(
        onTap: onTap,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
        leading: Icon(icon, color: AppTheme.primaryNavy, size: 20),
        title: Text(
          title,
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
          ),
        ),
        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16, color: AppTheme.textSecondaryLight),
      ),
    );
  }

  Widget _buildConnectedServicesTile(
    BuildContext context, {
    required bool gmailConnected,
    required bool whatsappConnected,
    required VoidCallback onTap,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.grid_view_rounded, color: AppTheme.primaryNavy, size: 20),
              const SizedBox(width: 14),
              Text(
                'Connected Services',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                ),
              ),
              const Spacer(),
              const Icon(Icons.arrow_forward_ios_rounded, size: 16, color: AppTheme.textSecondaryLight),
            ],
          ),
          const SizedBox(height: 14),

          // Gmail sub-row (Mockup 12)
          Padding(
            padding: const EdgeInsets.only(left: 34),
            child: Row(
              children: [
                const Icon(Icons.mail_rounded, color: AppTheme.redPriority, size: 18),
                const SizedBox(width: 10),
                const Text('Gmail', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                const Spacer(),
                const Text('Connected', style: TextStyle(fontSize: 12, color: AppTheme.textSecondaryLight)),
                const SizedBox(width: 6),
                Container(width: 7, height: 7, decoration: const BoxDecoration(color: AppTheme.greenWhatsApp, shape: BoxShape.circle)),
              ],
            ),
          ),
          const SizedBox(height: 10),

          // WhatsApp sub-row (Mockup 12)
          Padding(
            padding: const EdgeInsets.only(left: 34),
            child: Row(
              children: [
                const Icon(Icons.chat_bubble_rounded, color: AppTheme.greenWhatsApp, size: 18),
                const SizedBox(width: 10),
                const Text('WhatsApp', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                const Spacer(),
                const Text('Connected', style: TextStyle(fontSize: 12, color: AppTheme.textSecondaryLight)),
                const SizedBox(width: 6),
                Container(width: 7, height: 7, decoration: const BoxDecoration(color: AppTheme.greenWhatsApp, shape: BoxShape.circle)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
