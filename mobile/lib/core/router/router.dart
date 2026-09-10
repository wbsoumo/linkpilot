import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../features/splash/splash_screen.dart';
import '../../features/auth/welcome_screen.dart';
import '../../features/auth/website_handoff_screen.dart';
import '../../features/auth/login_screen.dart';
import '../../features/tasks/tasks_screen.dart';
import '../../shared/widgets/main_layout.dart';

final GoRouter appRouter = GoRouter(
  initialLocation: '/splash',
  routes: [
    GoRoute(
      path: '/splash',
      builder: (context, state) => const SplashScreen(),
    ),
    GoRoute(
      path: '/welcome',
      pageBuilder: (context, state) => CustomTransitionPage(
        key: state.pageKey,
        child: const WelcomeScreen(),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(opacity: animation, child: child);
        },
      ),
    ),
    GoRoute(
      path: '/website-auth',
      builder: (context, state) => const WebsiteHandoffScreen(),
    ),
    GoRoute(
      path: '/login',
      pageBuilder: (context, state) => CustomTransitionPage(
        key: state.pageKey,
        child: const LoginScreen(),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(
            opacity: CurveTween(curve: Curves.easeInOut).animate(animation),
            child: child,
          );
        },
      ),
    ),
    GoRoute(
      path: '/dashboard',
      pageBuilder: (context, state) => CustomTransitionPage(
        key: state.pageKey,
        child: const MainLayoutScreen(initialIndex: 0),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(opacity: animation, child: child);
        },
      ),
    ),
    GoRoute(
      path: '/emails',
      builder: (context, state) => const MainLayoutScreen(initialIndex: 1),
    ),
    GoRoute(
      path: '/whatsapp',
      builder: (context, state) => const MainLayoutScreen(initialIndex: 2),
    ),
    GoRoute(
      path: '/settings',
      builder: (context, state) => const MainLayoutScreen(initialIndex: 3),
    ),
    GoRoute(
      path: '/tasks',
      builder: (context, state) => const TasksScreen(),
    ),
  ],
);
