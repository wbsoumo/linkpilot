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
  ThemeNotifier() : super(ThemeMode.dark);

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
    _tryRecoverSession();
  }

  Future<void> _tryRecoverSession() async {
    state = state.copyWith(isLoading: true);
    final token = await _storage.getToken();
    final userJson = await _storage.getUserData();
    if (token != null && userJson != null) {
      try {
        final userData = jsonDecode(userJson) as Map<String, dynamic>;
        state = AuthState(isAuthenticated: true, token: token, user: userData);
      } catch (e) {
        await logout();
      }
    } else {
      state = AuthState();
    }
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
        state = AuthState(error: response.data['message'] ?? 'Login failed');
        return false;
      }
    } catch (e) {
      state = AuthState(error: 'Connection error during login');
      return false;
    }
  }

  Future<bool> loginWithGoogle(String idToken, {String? email}) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final response = await _client.loginWithGoogleToken('login', idToken, email: email);
      if (response.data['status'] == 'success') {
        final data = response.data['data'];
        if (data['action'] == 'login') {
          final token = data['token'];
          final user = data['user'];
          await _storage.saveToken(token);
          await _storage.saveUserData(jsonEncode(user));
          state = AuthState(isAuthenticated: true, token: token, user: user);
          return true;
        } else {
          state = AuthState(error: 'Google Account not registered yet on LinkPilot.');
          return false;
        }
      } else {
        state = AuthState(error: response.data['message'] ?? 'Google Login failed');
        return false;
      }
    } catch (e) {
      state = AuthState(error: 'Connection error during Google Sign-In');
      return false;
    }
  }

  Future<String?> fetchGoogleClientId() async {
    try {
      final response = await _client.getGoogleConfig();
      if (response.data['status'] == 'success') {
        return response.data['data']['client_id'] as String?;
      }
    } catch (e) {}
    return null;
  }

  Future<void> logout() async {
    await _storage.clearAll();
    state = AuthState();
  }

  Future<void> mockAuthenticate(Map<String, dynamic> user, String token) async {
    await _storage.saveToken(token);
    await _storage.saveUserData(jsonEncode(user));
    state = AuthState(isAuthenticated: true, token: token, user: user);
  }
}

final authStateProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final client = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  return AuthNotifier(client, storage);
});

final dashboardDataProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getDashboardData();
  if (response.data['status'] == 'success') {
    return response.data['data'] as Map<String, dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load dashboard data');
});

final emailsListProvider = FutureProvider.family<List<dynamic>, String>((ref, folder) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getEmails(folder: folder);
  if (response.data['status'] == 'success') {
    // If received_emails is empty, return empty list
    final data = response.data['data'];
    if (data is Map && data.containsKey('emails')) {
      return data['emails'] as List<dynamic>;
    }
    return [];
  }
  throw Exception(response.data['message'] ?? 'Failed to load emails');
});

final whatsappThreadsProvider = FutureProvider<List<dynamic>>((ref) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getWhatsAppThreads();
  if (response.data['status'] == 'success') {
    final data = response.data['data'];
    if (data is Map && data.containsKey('threads')) {
      return data['threads'] as List<dynamic>;
    }
    return [];
  }
  throw Exception(response.data['message'] ?? 'Failed to load threads');
});

final crmDealsProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final client = ref.watch(apiClientProvider);
  final response = await client.getCRMDeals(layout: 'kanban');
  if (response.data['status'] == 'success') {
    return response.data['data'] as Map<String, dynamic>;
  }
  throw Exception(response.data['message'] ?? 'Failed to load deals');
});

final crmContactsProvider = FutureProvider<List<dynamic>>((ref) async {
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
