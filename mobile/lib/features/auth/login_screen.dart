import 'dart:convert';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'dart:html' if (dart.library.io) 'package:mobile/core/utils/html_stub.dart' as html;
import '../../core/theme/theme.dart';
import '../../core/providers/providers.dart';
import '../../core/network/api_client.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {

  @override
  void initState() {
    super.initState();
    _checkForRedirectParams();
  }

  void _checkForRedirectParams() {
    if (kIsWeb) {
      try {
        final uri = Uri.parse(html.window.location.href);
        final token = uri.queryParameters['token'];
        final userJson = uri.queryParameters['user'];

        if (token != null && userJson != null) {
          final cleanUrl = html.window.location.href.split('?').first;
          html.window.history.replaceState({}, '', cleanUrl);

          WidgetsBinding.instance.addPostFrameCallback((_) async {
            final user = jsonDecode(userJson) as Map<String, dynamic>;
            await ref.read(authStateProvider.notifier).mockAuthenticate(user, token);
            if (mounted) {
              context.go('/dashboard');
            }
          });
        }
      } catch (e) {}
    }
  }

  void _authorizeLinkPilotAccount() {
    if (kIsWeb) {
      final currentUrl = html.window.location.href;
      final redirectUrl = '${ApiClient.baseUrl}/dashboard/authorize.html?redirect_uri=${Uri.encodeComponent(currentUrl)}';
      
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Redirecting to LinkPilot Account Authorization...')),
      );
      
      html.window.location.href = redirectUrl;
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Browser authorization redirection not supported on this platform.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authStateProvider);

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppTheme.premiumDarkGradient),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 28.0),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Brand Logo Area
                  Center(
                    child: Container(
                      padding: const EdgeInsets.all(24),
                      decoration: AppTheme.glassBox(
                        borderRadius: 32,
                      ),
                      child: const Icon(
                        Icons.radar_rounded,
                        size: 72,
                        color: AppTheme.primaryPurple,
                      ),
                    ),
                  ),
                  const SizedBox(height: 32),
                  
                  Center(
                    child: Text(
                      'LinkPilot Mobile',
                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            letterSpacing: 0.5,
                          ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  
                  const Center(
                    child: Padding(
                      padding: EdgeInsets.symmetric(horizontal: 16.0),
                      child: Text(
                        'Securely connect the mobile app with your LinkPilot web account to sync campaigns, emails, WhatsApp messages and CRM.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: AppTheme.textSecondaryDark,
                          fontSize: 14,
                          height: 1.4,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 48),

                  // Error indicator
                  if (authState.error != null) ...[
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppTheme.priorityOrange.withOpacity(0.15),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppTheme.priorityOrange.withOpacity(0.5)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.error_outline, color: AppTheme.priorityOrange, size: 20),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              authState.error!,
                              style: const TextStyle(color: AppTheme.priorityOrange, fontSize: 13),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),
                  ],

                  // Single SSO Action Button
                  ElevatedButton.icon(
                    onPressed: authState.isLoading ? null : _authorizeLinkPilotAccount,
                    icon: authState.isLoading 
                        ? const SizedBox.shrink()
                        : const Icon(Icons.vpn_key_outlined, size: 20),
                    label: authState.isLoading
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Text(
                            'Authorize LinkPilot Account',
                            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                          ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primaryPurple,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 18),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(20),
                      ),
                      elevation: 4,
                      shadowColor: AppTheme.primaryPurple.withOpacity(0.3),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
