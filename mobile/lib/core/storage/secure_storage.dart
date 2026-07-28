import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorageService {
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  Future<void> saveToken(String token) async {
    await _storage.write(key: 'jwt_token', value: token);
  }

  Future<String?> getToken() async {
    return await _storage.read(key: 'jwt_token');
  }

  Future<void> deleteToken() async {
    await _storage.delete(key: 'jwt_token');
  }

  Future<void> saveUserData(String jsonStr) async {
    await _storage.write(key: 'user_data', value: jsonStr);
  }

  Future<String?> getUserData() async {
    return await _storage.read(key: 'user_data');
  }

  Future<void> clearAll() async {
    await _storage.deleteAll();
  }
}
