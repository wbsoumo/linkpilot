import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiClient {
  final Dio dio;
  final FlutterSecureStorage storage;

  // Change this to match the backend server address (e.g. localhost or staging URL)
  static const String baseUrl = 'https://linkpilot.work';

  ApiClient()
      : dio = Dio(BaseOptions(
          baseUrl: baseUrl,
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 15),
        )),
        storage = const FlutterSecureStorage() {
    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await storage.read(key: 'jwt_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onError: (DioException error, handler) async {
        // Automatically handle token refresh or session recovery here if needed
        return handler.next(error);
      },
    ));
  }

  // --- Auth APIs ---
  Future<Response> login(String email, String password) async {
    return await dio.post('/backend/api/auth/login.php', data: {
      'email': email,
      'password': password,
    });
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

  Future<Response> register(String name, String email, String password, String phone) async {
    return await dio.post('/backend/api/auth/register.php', data: {
      'name': name,
      'email': email,
      'password': password,
      'phone_number': phone,
    });
  }

  // --- Profile APIs ---
  Future<Response> getProfile() async {
    return await dio.get('/backend/api/profile/get.php');
  }

  Future<Response> updateProfile(Map<String, dynamic> data) async {
    return await dio.post('/backend/api/profile/update.php', data: data);
  }

  // --- Dashboard Analytics APIs ---
  Future<Response> getDashboardData() async {
    return await dio.get('/backend/api/analytics/dashboard.php');
  }

  // --- Email Intelligence APIs ---
  Future<Response> getEmails({
    String folder = 'inbox',
    String search = '',
    String category = '',
    int page = 1,
  }) async {
    return await dio.get('/backend/api/crm/email_intelligence/emails.php', queryParameters: {
      'folder': folder,
      'search': search,
      'category': category,
      'page': page,
    });
  }

  Future<Response> getEmailDetails(int id, {String folder = 'inbox'}) async {
    return await dio.get('/backend/api/crm/email_intelligence/emails.php', queryParameters: {
      'id': id,
      'folder': folder,
    });
  }

  Future<Response> updateEmailState(int id, String action, dynamic state) async {
    // Action can be: 'read', 'starred', 'archived', 'delete'
    return await dio.post(
      '/backend/api/crm/email_intelligence/emails.php',
      queryParameters: {'action': action},
      data: {
        'id': id,
        'state': state,
      },
    );
  }

  Future<Response> sendEmail(String recipient, String subject, String body) async {
    return await dio.post('/backend/api/generate/send_email.php', data: {
      'recipient_email': recipient,
      'subject': subject,
      'body': body,
    });
  }

  // --- WhatsApp APIs ---
  Future<Response> getWhatsAppThreads({String search = '', String tag = ''}) async {
    return await dio.get('/backend/api/whatsapp/inbox.php', queryParameters: {
      'search': search,
      'tag': tag,
    });
  }

  Future<Response> getWhatsAppMessages(int waContactId) async {
    return await dio.get('/backend/api/whatsapp/inbox.php', queryParameters: {
      'action': 'messages',
      'wa_contact_id': waContactId,
    });
  }

  Future<Response> getAiReplySuggestion(int waContactId) async {
    return await dio.post('/backend/api/whatsapp/inbox.php', data: {
      'action': 'apply_ai_reply',
      'wa_contact_id': waContactId,
    });
  }

  Future<Response> sendWhatsAppMessage({
    required int waContactId,
    required String body,
    String type = 'text',
    String recipient = '',
  }) async {
    return await dio.post('/backend/api/whatsapp/inbox.php', data: {
      'wa_contact_id': waContactId,
      'body': body,
      'type': type,
      'recipient': recipient,
    });
  }

  // --- CRM Deals & Contacts APIs ---
  Future<Response> getCRMDeals({String layout = 'list', String search = ''}) async {
    return await dio.get('/backend/api/crm/deals.php', queryParameters: {
      'layout': layout,
      'search': search,
    });
  }

  Future<Response> getCRMDealDetails(int id) async {
    return await dio.get('/backend/api/crm/deals.php', queryParameters: {
      'id': id,
    });
  }

  Future<Response> createCRMDeal(Map<String, dynamic> dealData) async {
    return await dio.post('/backend/api/crm/deals.php', data: dealData);
  }

  Future<Response> updateCRMDeal(int id, Map<String, dynamic> dealData) async {
    return await dio.post('/backend/api/crm/deals.php', queryParameters: {'action': 'update'}, data: {
      'id': id,
      ...dealData,
    });
  }

  Future<Response> getCRMContacts({String search = '', int page = 1}) async {
    return await dio.get('/backend/api/crm/contacts.php', queryParameters: {
      'search': search,
      'page': page,
    });
  }

  Future<Response> getCRMMeetings() async {
    return await dio.get('/backend/api/crm/meetings.php');
  }

  Future<Response> getCRMTasks() async {
    return await dio.get('/backend/api/crm/tasks.php');
  }

  // --- AI Workspace APIs ---
  Future<Response> generateAIEmailReply(int emailId, String prompt) async {
    return await dio.post('/backend/api/crm/ai_email_writer.php', data: {
      'email_id': emailId,
      'prompt': prompt,
    });
  }

  Future<Response> chatWithAssistant(String message) async {
    return await dio.post('/backend/api/crm/chat_assistant.php', data: {
      'message': message,
    });
  }
}
