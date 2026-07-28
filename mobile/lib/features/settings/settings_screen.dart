import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/bottom_nav.dart';
import '../../core/widgets/ai_floating_button.dart';
import '../../core/providers/providers.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authStateProvider);
    final themeMode = ref.watch(themeStateProvider);
    final user = authState.user;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Settings & Hub',
          style: GoogleFonts.outfit(fontWeight: FontWeight.bold, color: Colors.white),
        ),
        backgroundColor: AppTheme.obsidianBlack,
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: AppTheme.premiumDarkGradient,
        ),
        child: ListView(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          children: [
            // Profile Card
            Container(
              padding: const EdgeInsets.all(20),
              decoration: AppTheme.glassBox(),
              child: Row(
                children: [
                  CircleAvatar(
                    backgroundColor: AppTheme.primaryPurple,
                    radius: 30,
                    child: Text(
                      user?['name']?[0] ?? 'U',
                      style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user?['name'] ?? 'LinkPilot User',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          user?['email'] ?? 'user@linkpilot.work',
                          style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 13),
                        ),
                        const SizedBox(height: 2),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppTheme.primaryPurple.withOpacity(0.2),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            user?['role']?.toUpperCase() ?? 'USER',
                            style: const TextStyle(color: AppTheme.secondaryPurple, fontSize: 10, fontWeight: FontWeight.bold),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Connection Statuses
            Text(
              'INTEGRATION STATUS',
              style: GoogleFonts.outfit(color: AppTheme.textSecondaryDark, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 1.5),
            ),
            const SizedBox(height: 8),
            _buildSettingTile(
              icon: Icons.mark_as_unread,
              title: 'Gmail / SMTP Settings',
              trailing: const Text('Connected', style: TextStyle(color: Colors.greenAccent, fontSize: 13, fontWeight: FontWeight.bold)),
              onTap: () {},
            ),
            _buildSettingTile(
              icon: Icons.phone_android,
              title: 'WhatsApp Business Cloud API',
              trailing: const Text('Connected', style: TextStyle(color: Colors.greenAccent, fontSize: 13, fontWeight: FontWeight.bold)),
              onTap: () {},
            ),
            const SizedBox(height: 24),

            // Campaigns Launcher
            Text(
              'CAMPAIGN CREATOR',
              style: GoogleFonts.outfit(color: AppTheme.textSecondaryDark, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 1.5),
            ),
            const SizedBox(height: 8),
            _buildSettingTile(
              icon: Icons.rocket_launch,
              title: 'Launch Campaigns & Automation Builder',
              subtitle: 'Redirects to LinkPilot Web in Desktop Mode',
              trailing: const Icon(Icons.arrow_forward_ios, color: Colors.white, size: 16),
              onTap: () {
                context.push('/campaigns');
              },
            ),
            const SizedBox(height: 24),

            // Preference Settings
            Text(
              'PREFERENCES',
              style: GoogleFonts.outfit(color: AppTheme.textSecondaryDark, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 1.5),
            ),
            const SizedBox(height: 8),
            _buildSettingTile(
              icon: themeMode == ThemeMode.dark ? Icons.dark_mode : Icons.light_mode,
              title: 'Dark Theme Mode',
              trailing: Switch(
                value: themeMode == ThemeMode.dark,
                onChanged: (value) {
                  ref.read(themeStateProvider.notifier).toggleTheme();
                },
                activeColor: AppTheme.primaryPurple,
              ),
              onTap: () {},
            ),
            _buildSettingTile(
              icon: Icons.fingerprint,
              title: 'Biometric Secure Lock',
              trailing: Switch(
                value: true,
                onChanged: (value) {},
                activeColor: AppTheme.primaryPurple,
              ),
              onTap: () {},
            ),
            const SizedBox(height: 24),

            // Logout Button
            ElevatedButton.icon(
              onPressed: () async {
                await ref.read(authStateProvider.notifier).logout();
                if (context.mounted) context.go('/onboarding');
              },
              icon: const Icon(Icons.logout, color: Colors.white),
              label: const Text('Sign Out Everywhere', style: TextStyle(fontWeight: FontWeight.bold)),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.priorityOrange,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
            const SizedBox(height: 40),
          ],
        ),
      ),
      floatingActionButton: const AiFloatingActionButton(),
      bottomNavigationBar: const LinkPilotBottomNav(currentIndex: 5),
    );
  }

  Widget _buildSettingTile({
    required IconData icon,
    required String title,
    String? subtitle,
    required Widget trailing,
    required VoidCallback onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: AppTheme.glassBox(),
      child: ListTile(
        leading: Icon(icon, color: AppTheme.secondaryPurple),
        title: Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 14)),
        subtitle: subtitle != null ? Text(subtitle, style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 11)) : null,
        trailing: trailing,
        onTap: onTap,
      ),
    );
  }
}
