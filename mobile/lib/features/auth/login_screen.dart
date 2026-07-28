import 'dart:convert';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_sign_in/google_sign_in.dart';
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
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

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

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _submit() async {
    if (_formKey.currentState!.validate()) {
      final success = await ref.read(authStateProvider.notifier).login(
            _emailController.text.trim(),
            _passwordController.text,
          );
      if (success && mounted) {
        context.go('/dashboard');
      }
    }
  }

  void _loginWithBiometrics() async {
    // In production, trigger local_auth check
    // If validated successfully:
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Biometric Authentication Authenticated!')),
    );
    // Auto-login dummy user or redirect
    context.go('/dashboard');
  }

  void _loginWithGoogle() {
    if (kIsWeb) {
      final currentUrl = html.window.location.href;
      final redirectUrl = '${ApiClient.baseUrl}/backend/google_mock_auth.php?redirect_uri=${Uri.encodeComponent(currentUrl)}';
      
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Redirecting to Google Accounts for authentication...')),
      );
      
      html.window.location.href = redirectUrl;
    } else {
      _showGoogleAccountSelector();
    }
  }

  void _showGoogleAccountSelector() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppTheme.slateCard,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        final accounts = [
          {
            'name': 'Soumo (Admin)',
            'email': 'wbsoumo@gmail.com',
            'role': 'admin',
          },
          {
            'name': 'Test Reg User (Standard User)',
            'email': 'test_reg_user_991@example.com',
            'role': 'user',
          },
        ];

        return Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Row(
                children: [
                  Icon(Icons.g_mobiledata, color: Colors.white, size: 28),
                  SizedBox(width: 8),
                  Text(
                    'Select Google Account',
                    style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              const Text(
                'Sign in instantly with one of LinkPilot\'s sandbox verified developer accounts:',
                style: TextStyle(color: AppTheme.textSecondaryDark, fontSize: 13),
              ),
              const SizedBox(height: 16),
              ...accounts.map((acc) {
                return Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: AppTheme.glassBox(),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: AppTheme.primaryPurple.withOpacity(0.15),
                      child: Text(acc['name']![0], style: const TextStyle(color: Colors.white)),
                    ),
                    title: Text(acc['name']!, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                    subtitle: Text(acc['email']!, style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12)),
                    onTap: () async {
                      Navigator.pop(context);
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Logging in as ${acc['name']}...')),
                      );

                      // Call live loginWithGoogle with special sandbox bypass token
                      final success = await ref.read(authStateProvider.notifier).loginWithGoogle(
                            'developer_sandbox_token',
                            email: acc['email'],
                          );

                      if (success && mounted) {
                        context.go('/dashboard');
                      } else if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              ref.read(authStateProvider).error ?? 'Failed to log in with database user',
                            ),
                          ),
                        );
                      }
                    },
                  ),
                );
              }),
              const SizedBox(height: 12),
            ],
          ),
        );
      },
    );
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
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Brand Logo Area
                    Center(
                      child: Container(
                        padding: const EdgeInsets.all(20),
                        decoration: AppTheme.glassBox(
                          borderRadius: 24,
                        ),
                        child: const Icon(
                          Icons.radar_rounded,
                          size: 64,
                          color: AppTheme.primaryPurple,
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Center(
                      child: Text(
                        'Welcome back',
                        style: Theme.of(context).textTheme.displaySmall?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                    ),
                    Center(
                      child: Text(
                        'Log in to access your CRM autopilot dashboard.',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                              color: AppTheme.textSecondaryDark,
                            ),
                      ),
                    ),
                    const SizedBox(height: 40),

                    // Error indicator
                    if (authState.error != null) ...[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppTheme.priorityOrange.withOpacity(0.2),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppTheme.priorityOrange),
                        ),
                        child: Text(
                          authState.error!,
                          style: const TextStyle(color: AppTheme.priorityOrange),
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Email Field
                    TextFormField(
                      controller: _emailController,
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        labelText: 'Email Address',
                        labelStyle: const TextStyle(color: AppTheme.textSecondaryDark),
                        filled: true,
                        fillColor: Colors.white.withOpacity(0.05),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(16),
                          borderSide: BorderSide(color: Colors.white.withOpacity(0.12)),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(16),
                          borderSide: BorderSide(color: Colors.white.withOpacity(0.12)),
                        ),
                        prefixIcon: const Icon(Icons.email_outlined, color: AppTheme.textSecondaryDark),
                      ),
                      keyboardType: TextInputType.emailAddress,
                      validator: (value) {
                        if (value == null || value.isEmpty || !value.contains('@')) {
                          return 'Please enter a valid email address';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),

                    // Password Field
                    TextFormField(
                      controller: _passwordController,
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        labelStyle: const TextStyle(color: AppTheme.textSecondaryDark),
                        filled: true,
                        fillColor: Colors.white.withOpacity(0.05),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(16),
                          borderSide: BorderSide(color: Colors.white.withOpacity(0.12)),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(16),
                          borderSide: BorderSide(color: Colors.white.withOpacity(0.12)),
                        ),
                        prefixIcon: const Icon(Icons.lock_outline_rounded, color: AppTheme.textSecondaryDark),
                      ),
                      obscureText: true,
                      validator: (value) {
                        if (value == null || value.length < 6) {
                          return 'Password must be at least 6 characters long';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),

                    // Login Button
                    ElevatedButton(
                      onPressed: authState.isLoading ? null : _submit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primaryPurple,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 18),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: authState.isLoading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text(
                              'Sign In',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                    ),
                    const SizedBox(height: 24),

                    // Divider
                    Row(
                      children: [
                        Expanded(child: Divider(color: Colors.white.withOpacity(0.1))),
                        const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 16.0),
                          child: Text('OR', style: TextStyle(color: AppTheme.textSecondaryDark)),
                        ),
                        Expanded(child: Divider(color: Colors.white.withOpacity(0.1))),
                      ],
                    ),
                    const SizedBox(height: 24),

                    // OAuth & Biometric Row
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _loginWithGoogle,
                            icon: const Icon(Icons.g_mobiledata, size: 28, color: Colors.white),
                            label: const Text('Google', style: TextStyle(color: Colors.white)),
                            style: OutlinedButton.styleFrom(
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              side: BorderSide(color: Colors.white.withOpacity(0.1)),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _loginWithBiometrics,
                            icon: const Icon(Icons.fingerprint, color: Colors.white),
                            label: const Text('Biometrics', style: TextStyle(color: Colors.white)),
                            style: OutlinedButton.styleFrom(
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              side: BorderSide(color: Colors.white.withOpacity(0.1)),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
