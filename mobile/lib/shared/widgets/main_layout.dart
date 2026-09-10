import 'package:flutter/material.dart';
import '../../core/theme/theme.dart';
import '../../features/dashboard/dashboard_screen.dart';
import '../../features/email/email_list_screen.dart';
import '../../features/whatsapp/whatsapp_inbox_screen.dart';
import '../../features/settings/settings_screen.dart';

class MainLayoutScreen extends StatefulWidget {
  final int initialIndex;
  const MainLayoutScreen({super.key, this.initialIndex = 0});

  @override
  State<MainLayoutScreen> createState() => _MainLayoutScreenState();
}

class _MainLayoutScreenState extends State<MainLayoutScreen> {
  late int _currentIndex;

  final List<Widget> _screens = const [
    DashboardScreen(),
    EmailListScreen(),
    WhatsAppInboxScreen(),
    SettingsScreen(),
  ];

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: isDark ? AppTheme.slateCard : Colors.white,
          border: Border(
            top: BorderSide(
              color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate,
              width: 1.0,
            ),
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildNavItem(0, Icons.home_filled, Icons.home_outlined, 'Home'),
                _buildNavItem(1, Icons.mail_rounded, Icons.mail_outline_rounded, 'Mail'),
                _buildNavItem(2, Icons.chat_bubble_rounded, Icons.chat_bubble_outline_rounded, 'WhatsApp'),
                _buildNavItem(3, Icons.settings_rounded, Icons.settings_outlined, 'Settings'),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(int index, IconData activeIcon, IconData inactiveIcon, String label) {
    final isSelected = _currentIndex == index;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return InkWell(
      onTap: () {
        setState(() {
          _currentIndex = index;
        });
      },
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isSelected ? activeIcon : inactiveIcon,
              color: isSelected
                  ? AppTheme.brandBlue
                  : (isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight),
              size: 24,
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                color: isSelected
                    ? AppTheme.brandBlue
                    : (isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
