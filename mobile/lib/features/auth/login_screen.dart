import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import '../../core/providers/providers.dart';
import '../../core/theme/theme.dart';
import 'dart:html' as html;

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _isGoogleLoading = false;
  bool _isHandoffAuthenticating = false;

  @override
  void initState() {
    super.initState();
    _checkUrlForAuthParams();
  }

  void _checkUrlForAuthParams() {
    try {
      final href = html.window.location.href;
      final uri = Uri.parse(href);
      
      String? token = uri.queryParameters['token'];
      String? userJson = uri.queryParameters['user'];
      
      // Also check if fragment contains params
      if (token == null && uri.hasFragment) {
        final fragUri = Uri.parse('http://dummy.com${uri.fragment}');
        token = fragUri.queryParameters['token'];
        userJson = fragUri.queryParameters['user'];
      }

      if (token != null && token.isNotEmpty) {
        setState(() {
          _isHandoffAuthenticating = true;
        });
        _completeHandoffAuth(token, userJson);
      }
    } catch (e) {
      debugPrint('Error checking URL params: $e');
    }
  }

  Future<void> _completeHandoffAuth(String token, String? userJson) async {
    try {
      // Update auth state in Riverpod (which also writes to storage and sets API client token)
      await ref.read(authStateProvider.notifier).setSession(token, userJson);

      await Future.delayed(const Duration(milliseconds: 800));

      if (mounted) {
        context.go('/dashboard');
      }
    } catch (e) {
      debugPrint('Handoff error: $e');
      if (mounted) {
        setState(() {
          _isHandoffAuthenticating = false;
        });
      }
    }
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    final email = _emailController.text.trim();
    final password = _passwordController.text;

    final success = await ref.read(authStateProvider.notifier).login(email, password);

    if (mounted && success) {
      context.go('/dashboard');
    }
  }

  Future<void> _redirectToWebsiteLogin() async {
    String currentOrigin = 'http://localhost:8080';
    if (kIsWeb) {
      currentOrigin = html.window.location.origin;
    }
    final redirectUri = Uri.encodeComponent('$currentOrigin/#/login');
    final websiteLoginUrl = 'https://linkpilot.work/dashboard/login.html?source=mobile_app&redirect_uri=$redirectUri';
    
    if (kIsWeb) {
      html.window.location.href = websiteLoginUrl;
    } else {
      final Uri url = Uri.parse(websiteLoginUrl);
      if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Could not launch Link Pilot website.')),
          );
        }
      }
    }
  }

  Future<void> _handleGoogleSignIn() async {
    setState(() => _isGoogleLoading = true);
    try {
      final GoogleSignIn googleSignIn = GoogleSignIn(
        scopes: ['email', 'profile'],
      );
      
      final GoogleSignInAccount? googleUser = await googleSignIn.signIn().catchError((err) {
        debugPrint('Google Sign In plugin error: $err');
        return null;
      });

      if (googleUser != null) {
        final GoogleSignInAuthentication? googleAuth = await googleUser.authentication.catchError((_) => null);
        if (googleAuth != null) {
          final idToken = googleAuth.idToken ?? googleAuth.accessToken;
          if (idToken != null && idToken.isNotEmpty) {
            final success = await ref.read(authStateProvider.notifier).loginWithGoogle(
                  idToken,
                  email: googleUser.email,
                );
            if (mounted && success) {
              context.go('/dashboard');
              return;
            }
          }
        }
      }

      // Handoff to website login if native Google Sign-In isn't available
      await _redirectToWebsiteLogin();

    } catch (e) {
      await _redirectToWebsiteLogin();
    } finally {
      if (mounted) setState(() => _isGoogleLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authStateProvider);

    if (_isHandoffAuthenticating) {
      return Scaffold(
        backgroundColor: Colors.white,
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: AppTheme.brandBlue.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const CircularProgressIndicator(
                  color: AppTheme.brandBlue,
                  strokeWidth: 3,
                ),
              ),
              const SizedBox(height: 24),
              const Text(
                'Authenticating Link Pilot App...',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.primaryNavy,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Completing authorization handoff. Please wait...',
                style: TextStyle(
                  fontSize: 13,
                  color: AppTheme.textSecondaryLight,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: AppTheme.primaryNavy, size: 20),
          onPressed: () => context.pop(),
        ),
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28.0, vertical: 12.0),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // App Icon / Logo
                  Center(
                    child: Transform.rotate(
                      angle: -0.2,
                      child: const Icon(
                        Icons.send_rounded,
                        size: 48,
                        color: AppTheme.brandBlue,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Welcome to Link Pilot',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      color: AppTheme.primaryNavy,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Sign in with your Link Pilot account',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 14,
                      color: AppTheme.textSecondaryLight,
                    ),
                  ),
                  const SizedBox(height: 28),

                  // Error banner
                  if (authState.error != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppTheme.redPriority.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: AppTheme.redPriority.withValues(alpha: 0.3),
                        ),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.error_outline, color: AppTheme.redPriority, size: 20),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              authState.error!,
                              style: const TextStyle(color: AppTheme.redPriority, fontSize: 13, fontWeight: FontWeight.w500),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                  ],

                  // Email field
                  const Text(
                    'Email Address',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.primaryNavy,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    style: const TextStyle(color: AppTheme.primaryNavy),
                    decoration: InputDecoration(
                      hintText: 'name@company.com',
                      hintStyle: const TextStyle(fontSize: 14, color: AppTheme.textSecondaryLight),
                      prefixIcon: const Icon(Icons.email_outlined, size: 20, color: AppTheme.textSecondaryLight),
                      filled: true,
                      fillColor: const Color(0xFFF8FAFC),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppTheme.borderSlate),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppTheme.borderSlate),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppTheme.brandBlue, width: 1.5),
                      ),
                    ),
                    validator: (value) {
                      if (value == null || value.trim().isEmpty) {
                        return 'Please enter your email';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 18),

                  // Password field
                  const Text(
                    'Password',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.primaryNavy,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextFormField(
                    controller: _passwordController,
                    obscureText: _obscurePassword,
                    style: const TextStyle(color: AppTheme.primaryNavy),
                    decoration: InputDecoration(
                      hintText: '••••••••',
                      hintStyle: const TextStyle(fontSize: 14, color: AppTheme.textSecondaryLight),
                      prefixIcon: const Icon(Icons.lock_outline_rounded, size: 20, color: AppTheme.textSecondaryLight),
                      suffixIcon: IconButton(
                        icon: Icon(_obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined, size: 20, color: AppTheme.textSecondaryLight),
                        onPressed: () {
                          setState(() {
                            _obscurePassword = !_obscurePassword;
                          });
                        },
                      ),
                      filled: true,
                      fillColor: const Color(0xFFF8FAFC),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppTheme.borderSlate),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppTheme.borderSlate),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppTheme.brandBlue, width: 1.5),
                      ),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please enter your password';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 24),

                  // Submit Button (Brand Blue)
                  ElevatedButton(
                    onPressed: authState.isLoading ? null : _handleLogin,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.brandBlue,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      elevation: 0,
                    ),
                    child: authState.isLoading
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Text(
                            'Sign In to Mobile',
                            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                          ),
                  ),

                  const SizedBox(height: 20),
                  Row(
                    children: const [
                      Expanded(child: Divider(color: AppTheme.borderSlate)),
                      Padding(
                        padding: EdgeInsets.symmetric(horizontal: 12),
                        child: Text(
                          'OR',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: AppTheme.textSecondaryLight,
                          ),
                        ),
                      ),
                      Expanded(child: Divider(color: AppTheme.borderSlate)),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Google Sign-In Button (Triggers Handoff)
                  OutlinedButton(
                    onPressed: _isGoogleLoading ? null : _handleGoogleSignIn,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppTheme.primaryNavy,
                      backgroundColor: Colors.white,
                      side: const BorderSide(color: AppTheme.borderSlate, width: 1.5),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: _isGoogleLoading
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.brandBlue),
                          )
                        : Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: const [
                              Icon(Icons.g_mobiledata_rounded, size: 28, color: Colors.redAccent),
                              SizedBox(width: 6),
                              Text(
                                'Sign in with Google',
                                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                  ),

                  const SizedBox(height: 20),

                  // Website Handoff Button
                  OutlinedButton.icon(
                    onPressed: _redirectToWebsiteLogin,
                    icon: const Icon(Icons.open_in_browser_rounded, size: 18),
                    label: const Text('Sign in / Register via Website'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppTheme.textSecondaryLight,
                      side: const BorderSide(color: AppTheme.borderSlate),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
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

