import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/theme.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final PageController _pageController = PageController();
  int _currentIndex = 0;

  final List<OnboardingSlide> _slides = [
    OnboardingSlide(
      title: 'Unified Communication',
      description: 'Manage your client emails and WhatsApp messages in a single premium inbox powered by AI.',
      icon: Icons.chat_bubble_outline,
    ),
    OnboardingSlide(
      title: 'AI Autopilot Autoreply',
      description: 'Automatically draft intelligent context-aware replies to leads using semantic history recall.',
      icon: Icons.bolt,
    ),
    OnboardingSlide(
      title: 'Actionable CRM Insights',
      description: 'Track contact timelines, companies, lead ratings, and deals pipelines on the fly.',
      icon: Icons.insights,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: AppTheme.premiumDarkGradient,
        ),
        child: SafeArea(
          child: Column(
            children: [
              // Top Bar
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 16.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'LINKPILOT',
                      style: GoogleFonts.outfit(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                        letterSpacing: 2,
                      ),
                    ),
                    TextButton(
                      onPressed: () => _pageController.jumpToPage(3),
                      child: const Text(
                        'Skip',
                        style: TextStyle(color: AppTheme.secondaryPurple),
                      ),
                    ),
                  ],
                ),
              ),

              // Page View
              Expanded(
                child: PageView.builder(
                  controller: _pageController,
                  onPageChanged: (index) {
                    setState(() {
                      _currentIndex = index;
                    });
                  },
                  itemCount: 4, // 3 onboarding slides + 1 permissions setup slide
                  itemBuilder: (context, index) {
                    if (index == 3) {
                      return const PermissionsSetupWidget();
                    }
                    final slide = _slides[index];
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 32.0),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(32),
                            decoration: AppTheme.glassBox(),
                            child: Icon(
                              slide.icon,
                              size: 100,
                              color: AppTheme.primaryPurple,
                            ),
                          ),
                          const SizedBox(height: 48),
                          Text(
                            slide.title,
                            textAlign: TextAlign.center,
                            style: textTheme.displaySmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            slide.description,
                            textAlign: TextAlign.center,
                            style: textTheme.bodyLarge?.copyWith(
                              color: AppTheme.textSecondaryDark,
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),

              // Bottom Indicator / Action Button
              Padding(
                padding: const EdgeInsets.all(32.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    // Indicators
                    Row(
                      children: List.generate(
                        4,
                        (index) => AnimatedContainer(
                          duration: const Duration(milliseconds: 300),
                          margin: const EdgeInsets.only(right: 8.0),
                          height: 8.0,
                          width: _currentIndex == index ? 24.0 : 8.0,
                          decoration: BoxDecoration(
                            color: _currentIndex == index
                                ? AppTheme.primaryPurple
                                : AppTheme.slateBorder,
                            borderRadius: BorderRadius.circular(4.0),
                          ),
                        ),
                      ),
                    ),

                    // Next / Continue Button
                    ElevatedButton(
                      onPressed: () {
                        if (_currentIndex < 3) {
                          _pageController.nextPage(
                            duration: const Duration(milliseconds: 400),
                            curve: Curves.easeInOut,
                          );
                        } else {
                          context.go('/login');
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primaryPurple,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: Text(
                        _currentIndex == 3 ? 'Get Started' : 'Next',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class OnboardingSlide {
  final String title;
  final String description;
  final IconData icon;

  OnboardingSlide({
    required this.title,
    required this.description,
    required this.icon,
  });
}

class PermissionsSetupWidget extends StatelessWidget {
  const PermissionsSetupWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 16.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Permissions Setup',
            style: GoogleFonts.outfit(
              fontSize: 28,
              fontWeight: FontWeight.bold,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'LinkPilot requires following access to function properly as your workspace hub:',
            style: TextStyle(color: AppTheme.textSecondaryDark),
          ),
          const SizedBox(height: 24),
          _PermissionItem(
            icon: Icons.notifications_none,
            title: 'Notifications',
            desc: 'Used to alert you instantly about high-priority inbox emails and client WhatsApp responses.',
          ),
          _PermissionItem(
            icon: Icons.contacts_outlined,
            title: 'Contacts Sync',
            desc: 'Allows importing client device contacts directly into CRM portfolios.',
          ),
          _PermissionItem(
            icon: Icons.fingerprint,
            title: 'Biometric Authentication',
            desc: 'Locks the app to secure proprietary CRM client data and billing wallets.',
          ),
          _PermissionItem(
            icon: Icons.calendar_today_outlined,
            title: 'Calendar Integration',
            desc: 'Retrieves client schedules for today\'s meetings and follow-up agendas.',
          ),
          _PermissionItem(
            icon: Icons.camera_alt_outlined,
            title: 'Camera & Storage',
            desc: 'Needed to attach screenshots and documents to email campaigns or WhatsApp replies.',
          ),
        ],
      ),
    );
  }
}

class _PermissionItem extends StatefulWidget {
  final IconData icon;
  final String title;
  final String desc;

  const _PermissionItem({
    required this.icon,
    required this.title,
    required this.desc,
  });

  @override
  State<_PermissionItem> createState() => _PermissionItemState();
}

class _PermissionItemState extends State<_PermissionItem> {
  bool _granted = false;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: AppTheme.glassBox(),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(widget.icon, size: 28, color: AppTheme.secondaryPurple),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.title,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  widget.desc,
                  style: const TextStyle(
                    color: AppTheme.textSecondaryDark,
                    fontSize: 13,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          TextButton(
            onPressed: () {
              setState(() {
                _granted = true;
              });
            },
            style: TextButton.styleFrom(
              backgroundColor: _granted ? Colors.green.withOpacity(0.2) : AppTheme.primaryPurple.withOpacity(0.2),
            ),
            child: Text(
              _granted ? 'Allowed' : 'Allow',
              style: TextStyle(
                color: _granted ? Colors.greenAccent : AppTheme.secondaryPurple,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
