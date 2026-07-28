import 'package:flutter/material.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/theme/theme.dart';
import '../../core/providers/providers.dart';

class WebCampaignsScreen extends ConsumerStatefulWidget {
  const WebCampaignsScreen({super.key});

  @override
  ConsumerState<WebCampaignsScreen> createState() => _WebCampaignsScreenState();
}

class _WebCampaignsScreenState extends ConsumerState<WebCampaignsScreen> {
  InAppWebViewController? _webViewController;
  bool _isLoading = true;

  static const String desktopUserAgent =
      'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authStateProvider);
    final token = authState.token ?? '';

    // Pass the token as a query parameter so the backend knows to auto-login or set session cookies
    final String url = 'https://linkpilot.work/dashboard/campaigns.html?token=$token';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Campaigns Builder', style: TextStyle(color: Colors.white)),
        backgroundColor: AppTheme.obsidianBlack,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () async {
            if (await _webViewController?.canGoBack() ?? false) {
              _webViewController?.goBack();
            } else {
              if (context.mounted) Navigator.pop(context);
            }
          },
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white),
            onPressed: () {
              _webViewController?.reload();
            },
          ),
        ],
      ),
      body: Stack(
        children: [
          InAppWebView(
            initialUrlRequest: URLRequest(
              url: WebUri(url),
            ),
            initialSettings: InAppWebViewSettings(
              userAgent: desktopUserAgent,
              preferredContentMode: UserPreferredContentMode.DESKTOP,
              javaScriptEnabled: true,
              domStorageEnabled: true,
              useWideViewPort: true,
              loadWithOverviewMode: true,
            ),
            onWebViewCreated: (controller) {
              _webViewController = controller;
            },
            onLoadStart: (controller, url) {
              setState(() {
                _isLoading = true;
              });
            },
            onLoadStop: (controller, url) async {
              setState(() {
                _isLoading = false;
              });

              // Inject Javascript to set authentication token directly into local storage
              // so that any internal fetch API calls are fully authenticated
              if (token.isNotEmpty) {
                await controller.evaluateJavascript(source: """
                  localStorage.setItem('jwt_token', '$token');
                  console.log('Session token injected successfully.');
                """);
              }
            },
          ),
          if (_isLoading)
            const Center(
              child: CircularProgressIndicator(
                color: AppTheme.primaryPurple,
              ),
            ),
        ],
      ),
    );
  }
}
