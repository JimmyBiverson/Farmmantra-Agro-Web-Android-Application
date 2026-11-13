class ApiEndpoints {
  ApiEndpoints._();

  // Plain flutter run/build connects to the hosted Laravel HTTPS API.
  // For local development, explicitly override API_BASE_URL:
  // Chrome/web:       http://localhost:8000/api/v1
  // Android emulator: http://10.0.2.2:8000/api/v1
  // Physical device:  http://<your-pc-ip>:8000/api/v1
  //
  // To switch: change the value below, or use --dart-define=API_BASE_URL=...
  // from the command line when building.
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://farmmantra.duckdns.org/api/v1',
  );

  // Auth
  static const String login = '/login';
  static const String logout = '/logout';
  static const String me = '/me';
  static const String updateProfile = '/profile';
  static const String changePassword = '/change-password';

  // Master Data
  static const String categories = '/categories';
  static const String products = '/products';

  // Notifications
  static const String notifications = '/notifications';
  static const String notificationsReadAll = '/notifications/read-all';
  static const String notificationsUnreadCount = '/notifications/unread-count';

  // Stock Movements
  static const String stockMovements = '/stock-movements';

  // Chat
  static const String conversations = '/conversations';
}
