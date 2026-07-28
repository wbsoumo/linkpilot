import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../theme/theme.dart';

class LinkPilotBottomNav extends StatelessWidget {
  final int currentIndex;

  const LinkPilotBottomNav({
    super.key,
    required this.currentIndex,
  });

  void _onTap(BuildContext context, int index) {
    if (index == currentIndex) return;

    switch (index) {
      case 0:
        context.go('/dashboard');
        break;
      case 1:
        context.go('/emails');
        break;
      case 2:
        context.go('/whatsapp');
        break;
      case 3:
        context.go('/crm');
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    
    final items = [
      {'icon': Icons.dashboard_outlined, 'activeIcon': Icons.dashboard, 'label': 'Home'},
      {'icon': Icons.email_outlined, 'activeIcon': Icons.email, 'label': 'Inbox'},
      {'icon': Icons.forum_outlined, 'activeIcon': Icons.forum, 'label': 'WhatsApp'},
      {'icon': Icons.people_alt_outlined, 'activeIcon': Icons.people_alt, 'label': 'CRM'},
    ];

    return Container(
      margin: const EdgeInsets.only(left: 20, right: 20, bottom: 24),
      height: 64,
      decoration: BoxDecoration(
        color: isDark ? Colors.black.withOpacity(0.4) : Colors.white.withOpacity(0.85),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isDark 
              ? Colors.white.withOpacity(0.08) 
              : Colors.black.withOpacity(0.05),
          width: 1.5,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(isDark ? 0.3 : 0.08),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(24),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 10, sigmaY: 10),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Stack(
              children: [
                // Sliding indicator background pill
                AnimatedAlign(
                  duration: const Duration(milliseconds: 250),
                  curve: Curves.fastOutSlowIn,
                  alignment: Alignment(
                    -1.0 + (currentIndex * (2.0 / (items.length - 1))),
                    0.0,
                  ),
                  child: FractionallySizedBox(
                    widthFactor: 1 / items.length,
                    child: Container(
                      height: 46,
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [
                            AppTheme.primaryPurple,
                            AppTheme.secondaryPurple,
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: AppTheme.primaryPurple.withOpacity(0.3),
                            blurRadius: 8,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                
                // Navigation items
                Row(
                  children: List.generate(items.length, (index) {
                    final item = items[index];
                    final isSelected = index == currentIndex;
                    
                    return Expanded(
                      child: GestureDetector(
                        behavior: HitTestBehavior.opaque,
                        onTap: () => _onTap(context, index),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          alignment: Alignment.center,
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                isSelected ? item['activeIcon'] as IconData : item['icon'] as IconData,
                                color: isSelected 
                                    ? Colors.white 
                                    : (isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight),
                                size: 20,
                              ),
                              const SizedBox(height: 2),
                              Text(
                                item['label'] as String,
                                style: TextStyle(
                                  color: isSelected 
                                      ? Colors.white 
                                      : (isDark ? AppTheme.textSecondaryDark : AppTheme.textSecondaryLight),
                                  fontSize: 10,
                                  fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  }),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
