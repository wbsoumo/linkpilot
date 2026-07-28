import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../features/onboarding/onboarding_screen.dart';
import '../../features/auth/login_screen.dart';
import '../../features/dashboard/dashboard_screen.dart';
import '../../features/email/email_list_screen.dart';
import '../../features/whatsapp/whatsapp_inbox_screen.dart';
import '../../features/crm/crm_deals_screen.dart';
import '../../features/ai/ai_workspace_screen.dart';
import '../../features/settings/settings_screen.dart';
import '../../features/campaigns/web_campaigns_screen.dart';

final GoRouter appRouter = GoRouter(
  initialLocation: '/onboarding',
  routes: [
    GoRoute(
      path: '/onboarding',
      builder: (context, state) => const OnboardingScreen(),
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
        child: const DashboardScreen(),
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          return FadeTransition(opacity: animation, child: child);
        },
      ),
    ),
    GoRoute(
      path: '/emails',
      builder: (context, state) => const EmailListScreen(),
    ),
    GoRoute(
      path: '/whatsapp',
      builder: (context, state) => const WhatsAppInboxScreen(),
    ),
    GoRoute(
      path: '/crm',
      builder: (context, state) => const CrmDealsScreen(),
    ),
    GoRoute(
      path: '/ai',
      builder: (context, state) => const AiWorkspaceScreen(),
    ),
    GoRoute(
      path: '/settings',
      builder: (context, state) => const SettingsScreen(),
    ),
    GoRoute(
      path: '/campaigns',
      builder: (context, state) => const WebCampaignsScreen(),
    ),
  ],
);
