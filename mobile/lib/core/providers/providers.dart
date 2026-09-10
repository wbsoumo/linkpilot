import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../network/api_client.dart';
import '../storage/secure_storage.dart';

final secureStorageProvider = Provider<SecureStorageService>((ref) {
  return SecureStorageService();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient();
});

// Theme provider (ThemeMode)
class ThemeNotifier extends StateNotifier<ThemeMode> {
  ThemeNotifier() : super(ThemeMode.light);

  void toggleTheme() {
    state = state == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark;
  }

  void setThemeMode(ThemeMode mode) {
    state = mode;
  }
}

final themeStateProvider = StateNotifierProvider<ThemeNotifier, ThemeMode>((ref) {
  return ThemeNotifier();
});

// Authentication state holding logged user model
class AuthState {
  final bool isAuthenticated;
  final String? token;
  final Map<String, dynamic>? user;
  final String? error;
  final bool isLoading;

  AuthState({
    this.isAuthenticated = false,
    this.token,
    this.user,
    this.error,
    this.isLoading = false,
  });

  AuthState copyWith({
    bool? isAuthenticated,
    String? token,
    Map<String, dynamic>? user,
    String? error,
    bool? isLoading,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      token: token ?? this.token,
      user: user ?? this.user,
      error: error ?? this.error,
      isLoading: isLoading ?? this.isLoading,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  final ApiClient _client;
  final SecureStorageService _storage;

  AuthNotifier(this._client, this._storage) : super(AuthState()) {
    tryRecoverSession();
  }

  Future<void> tryRecoverSession() async {
    state = state.copyWith(isLoading: true);
    try {
      final token = await _storage.getToken();
      final userJson = await _storage.getUserData();
      if (token != null && token.isNotEmpty) {
        try {
          final response = await _client.verifySession();
          if (response.data['status'] == 'success') {
            final user = response.data['data']['user'];
            await _storage.saveUserData(jsonEncode(user));
            state = AuthState(isAuthenticated: true, token: token, user: user);
            return;
          }
        } catch (e) {
          if (userJson != null) {
            try {
              final userData = jsonDecode(userJson) as Map<String, dynamic>;
              state = AuthState(isAuthenticated: true, token: token, user: userData);
              return;
            } catch (_) {}
          }
        }
      }
    } catch (_) {}
    state = AuthState(isAuthenticated: false);
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _client.login(email, password);
      if (response.data['status'] == 'success') {
        final data = response.data['data'];
        final token = data['token'];
        final user = data['user'];
        await _storage.saveToken(token);
        await _storage.saveUserData(jsonEncode(user));
        state = AuthState(isAuthenticated: true, token: token, user: user);
        return true;
      } else {
        state = state.copyWith(isLoading: false, error: response.data['message'] ?? 'Login failed');
        return false;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: 'Network error during login');
      return false;
    }
  }

  Future<bool> loginWithGoogle(String idToken, {String? email}) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _client.loginWithGoogleToken('login', idToken, email: email);
      if (response.data['status'] == 'success') {
        final data = response.data['data'];
        final token = data['token'];
        final user = data['user'];
        if (token != null) {
          await _storage.saveToken(token);
          await _storage.saveUserData(jsonEncode(user));
          state = AuthState(isAuthenticated: true, token: token, user: user);
          return true;
        }
      }
      state = state.copyWith(isLoading: false, error: 'Google Account not registered on LinkPilot website yet.');
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: 'Google Sign-In connection error');
      return false;
    }
  }

  Future<void> setSession(String token, String? userJson) async {
    await _storage.saveToken(token);
    Map<String, dynamic>? userData;
    if (userJson != null && userJson.isNotEmpty) {
      try {
        await _storage.saveUserData(userJson);
        userData = jsonDecode(userJson) as Map<String, dynamic>;
      } catch (_) {}
    }
    state = AuthState(isAuthenticated: true, token: token, user: userData);
  }

  Future<void> logout() async {
    await _storage.clearAll();
    await _client.clearToken();
    state = AuthState();
  }
}

final authStateProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final client = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  return AuthNotifier(client, storage);
});

// Mobile Dashboard Provider
final mobileDashboardProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getMobileDashboard();
  if (response.data['status'] == 'success') {
    return response.data['data'] as Map<String, dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load mobile dashboard data');
});

// Mobile Tasks Provider
final mobileTasksProvider = FutureProvider.family.autoDispose<List<dynamic>, String>((ref, filter) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getTasks(filter: filter);
  if (response.data['status'] == 'success') {
    return response.data['data']['tasks'] as List<dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load tasks');
});

// Mobile Email List Provider
final mobileEmailsProvider = FutureProvider.family.autoDispose<Map<String, dynamic>, String>((ref, search) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getEmails(search: search);
  if (response.data['status'] == 'success') {
    return response.data['data'] as Map<String, dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load emails');
});

// Mobile WhatsApp Conversations Provider
final mobileWhatsAppProvider = FutureProvider.family.autoDispose<Map<String, dynamic>, String>((ref, search) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getWhatsAppConversations(search: search);
  if (response.data['status'] == 'success') {
    return response.data['data'] as Map<String, dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load WhatsApp conversations');
});

// Mobile WhatsApp Chat Messages Provider
final mobileWhatsAppMessagesProvider = FutureProvider.family.autoDispose<Map<String, dynamic>, int>((ref, waContactId) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getWhatsAppMessages(waContactId);
  if (response.data['status'] == 'success') {
    return response.data['data'] as Map<String, dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load chat messages');
});

// Mobile Settings Provider
final mobileSettingsProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getSettings();
  if (response.data['status'] == 'success') {
    return response.data['data'] as Map<String, dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load settings');
});

// Legacy / Secondary Providers for extra CRM screens
final crmDealsProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getCRMDeals(layout: 'kanban');
  if (response.data['status'] == 'success') {
    return response.data['data'] as Map<String, dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load deals');
});

final crmContactsProvider = FutureProvider.autoDispose<List<dynamic>>((ref) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getCRMContacts();
  if (response.data['status'] == 'success') {
    final data = response.data['data'];
    if (data is Map && data.containsKey('contacts')) {
      return data['contacts'] as List<dynamic>;
    }
    return [];
  }
  throw Exception(response.data['message'] ?? 'Failed to load contacts');
});
