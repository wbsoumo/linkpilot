import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorageService {
  final FlutterSecureStorage _storage = const FlutterSecureStorage(
    webOptions: WebOptions(
      dbName: 'linkpilot_db',
      publicKey: 'linkpilot_public_key',
    ),
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  Future<void> saveToken(String token) async {
    try {
      await _storage.write(key: 'jwt_token', value: token);
    } catch (e) {
      debugPrint('Error saving token: $e');
    }
  }

  Future<String?> getToken() async {
    try {
      return await _storage.read(key: 'jwt_token');
    } catch (e) {
      debugPrint('Error reading token: $e');
      return null;
    }
  }

  Future<void> deleteToken() async {
    try {
      await _storage.delete(key: 'jwt_token');
    } catch (e) {
      debugPrint('Error deleting token: $e');
    }
  }

  Future<void> saveUserData(String jsonStr) async {
    try {
      await _storage.write(key: 'user_data', value: jsonStr);
    } catch (e) {
      debugPrint('Error saving user data: $e');
    }
  }

  Future<String?> getUserData() async {
    try {
      return await _storage.read(key: 'user_data');
    } catch (e) {
      debugPrint('Error reading user data: $e');
      return null;
    }
  }

  Future<void> clearAll() async {
    try {
      await _storage.deleteAll();
    } catch (e) {
      debugPrint('Error clearing storage: $e');
    }
  }
}
