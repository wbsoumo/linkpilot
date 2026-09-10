import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/theme.dart';

class WebsiteHandoffScreen extends StatelessWidget {
  const WebsiteHandoffScreen({super.key});

  Future<void> _openWebsite(BuildContext context) async {
    final Uri url = Uri.parse('https://linkpilot.work/dashboard/login.html');
    if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not launch Link Pilot website.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
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
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28.0, vertical: 20.0),
          child: Column(
            children: [
              const Spacer(),
              // Circular Link Icon
              Container(
                width: 90,
                height: 90,
                decoration: const BoxDecoration(
                  color: Color(0xFFEFF6FF),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.link_rounded,
                  size: 44,
                  color: AppTheme.brandBlue,
                ),
              ),
              const SizedBox(height: 28),
              const Text(
                'Continue on Link Pilot\nWebsite',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppTheme.primaryNavy,
                  height: 1.3,
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'You\'ll be redirected to complete\nlogin, account creation, or initial setup\non our website.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  height: 1.4,
                  color: AppTheme.textSecondaryLight,
                ),
              ),
              const Spacer(),

              // Open Website Button (Navy)
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => _openWebsite(context),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryNavy,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    elevation: 0,
                  ),
                  child: const Text(
                    'Open Website',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Back Link
              GestureDetector(
                onTap: () => context.pop(),
                child: const Text(
                  'Back',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: AppTheme.brandBlue,
                  ),
                ),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}
