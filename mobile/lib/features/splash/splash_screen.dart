import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers/providers.dart';
import '../../core/theme/theme.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> with SingleTickerProviderStateMixin {
  late AnimationController _animController;

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1500),
    )..forward();

    _checkSessionAndNavigate();
  }

  Future<void> _checkSessionAndNavigate() async {
    await Future.delayed(const Duration(milliseconds: 1800));
    if (!mounted) return;

    final authState = ref.read(authStateProvider);
    if (authState.isAuthenticated) {
      context.go('/dashboard');
    } else {
      context.go('/welcome');
    }
  }

  @override
  void dispose() {
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            const Spacer(),
            // Blue Paper Plane Logo
            Center(
              child: Transform.rotate(
                angle: -0.2,
                child: const Icon(
                  Icons.send_rounded,
                  size: 96,
                  color: AppTheme.brandBlue,
                ),
              ),
            ),
            const SizedBox(height: 24),
            // App Name
            Text(
              'Link Pilot',
              style: Theme.of(context).textTheme.displayLarge?.copyWith(
                    fontSize: 34,
                    fontWeight: FontWeight.w800,
                    color: AppTheme.primaryNavy,
                    letterSpacing: -0.5,
                  ),
            ),
            const SizedBox(height: 40),
            // Progress Indicator Bar
            SizedBox(
              width: 120,
              height: 4,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(2),
                child: const LinearProgressIndicator(
                  backgroundColor: Color(0xFFE2E8F0),
                  valueColor: AlwaysStoppedAnimation<Color>(AppTheme.brandBlue),
                ),
              ),
            ),
            const SizedBox(height: 20),
            // Tagline
            const Text(
              'Your Work, Connected',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
                color: AppTheme.textSecondaryLight,
              ),
            ),
            const Spacer(),
          ],
        ),
      ),
    );
  }
}
