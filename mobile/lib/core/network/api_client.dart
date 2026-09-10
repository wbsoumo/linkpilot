import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiClient {
  final Dio dio;
  final FlutterSecureStorage storage;

  // Base URL pointing to LinkPilot backend
  static const String baseUrl = 'https://linkpilot.work';

  ApiClient()
      : dio = Dio(BaseOptions(
          baseUrl: baseUrl,
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 15),
        )),
        storage = const FlutterSecureStorage(
          webOptions: WebOptions(
            dbName: 'linkpilot_db',
            publicKey: 'linkpilot_public_key',
          ),
          aOptions: AndroidOptions(encryptedSharedPreferences: true),
        ) {
    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await storage.read(key: 'jwt_token');
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onError: (DioException error, handler) async {
        return handler.next(error);
      },
    ));
  }

  // --- Auth APIs ---
  Future<Response> login(String email, String password) async {
    return await dio.post('/backend/api/mobile/auth.php?action=login', data: {
      'email': email,
      'password': password,
    });
  }

  Future<Response> verifySession() async {
    return await dio.get('/backend/api/mobile/auth.php');
  }

  Future<Response> getGoogleConfig() async {
    return await dio.get('/backend/api/auth/google_config.php');
  }

  Future<Response> loginWithGoogleToken(String action, String idToken, {String? email}) async {
    return await dio.post('/backend/api/auth/google_auth.php', data: {
      'action': action,
      'id_token': idToken,
      if (email != null) 'email': email,
    });
  }

  // --- Mobile Dashboard API ---
  Future<Response> getMobileDashboard() async {
    return await dio.get('/backend/api/mobile/dashboard.php');
  }

  // --- Mobile Tasks APIs ---
  Future<Response> getTasks({String filter = 'all', String search = '', String status = ''}) async {
    return await dio.get('/backend/api/mobile/tasks.php', queryParameters: {
      'filter': filter,
      'search': search,
      'status': status,
    });
  }

  Future<Response> createTask({
    required String title,
    String? description,
    String? dueDate,
    String? dueTime,
    String priority = 'medium',
  }) async {
    return await dio.post('/backend/api/mobile/tasks.php', data: {
      'title': title,
      'description': description,
      'due_date': dueDate,
      'due_time': dueTime,
      'priority': priority,
      'status': 'pending',
    });
  }

  Future<Response> updateTask(int id, Map<String, dynamic> data) async {
    return await dio.put('/backend/api/mobile/tasks.php', data: {
      'id': id,
      ...data,
    });
  }

  Future<Response> deleteTask(int id) async {
    return await dio.delete('/backend/api/mobile/tasks.php', data: {'id': id});
  }

  // --- Mobile Email APIs ---
  Future<Response> getEmails({String folder = 'inbox', String search = ''}) async {
    return await dio.get('/backend/api/mobile/email.php', queryParameters: {
      'folder': folder,
      'search': search,
    });
  }

  Future<Response> getEmailDetail(int id) async {
    return await dio.get('/backend/api/mobile/email.php', queryParameters: {'id': id});
  }

  Future<Response> sendEmail(String recipient, String subject, String body) async {
    return await dio.post('/backend/api/mobile/email.php', data: {
      'recipient_email': recipient,
      'subject': subject,
      'body': body,
    });
  }

  // --- Mobile WhatsApp APIs ---
  Future<Response> getWhatsAppConversations({String search = ''}) async {
    return await dio.get('/backend/api/mobile/whatsapp.php', queryParameters: {'search': search});
  }

  Future<Response> getWhatsAppMessages(int waContactId) async {
    return await dio.get('/backend/api/mobile/whatsapp.php', queryParameters: {
      'action': 'messages',
      'wa_contact_id': waContactId,
    });
  }

  Future<Response> sendWhatsAppMessage(int waContactId, String body) async {
    return await dio.post('/backend/api/mobile/whatsapp.php', data: {
      'wa_contact_id': waContactId,
      'body': body,
    });
  }

  // --- Settings API ---
  Future<Response> getSettings() async {
    return await dio.get('/backend/api/mobile/settings.php');
  }

  // --- Legacy / CRM Helper Compatibility ---
  Future<Response> getCRMDeals({String layout = 'list', String search = ''}) async {
    return await dio.get('/backend/api/crm/deals.php', queryParameters: {'layout': layout, 'search': search});
  }

  Future<Response> updateCRMDeal(int id, Map<String, dynamic> dealData) async {
    return await dio.post('/backend/api/crm/deals.php', queryParameters: {'action': 'update'}, data: {'id': id, ...dealData});
  }

  Future<Response> getCRMContacts({String search = '', int page = 1}) async {
    return await dio.get('/backend/api/crm/contacts.php', queryParameters: {'search': search, 'page': page});
  }

  Future<Response> chatWithAssistant(String message) async {
    return await dio.post('/backend/api/crm/chat_assistant.php', data: {'message': message});
  }

  // --- Token Helper Methods ---
  Future<void> saveToken(String token) async {
    await storage.write(key: 'jwt_token', value: token);
  }

  Future<String?> getToken() async {
    return await storage.read(key: 'jwt_token');
  }

  Future<void> clearToken() async {
    await storage.delete(key: 'jwt_token');
  }
}
