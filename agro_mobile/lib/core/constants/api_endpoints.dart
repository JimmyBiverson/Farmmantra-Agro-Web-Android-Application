class ApiEndpoints {
  ApiEndpoints._();

  // Chrome/web:      http://localhost:8000/api
  // Android emulator: http://10.0.2.2:8000/api
  // Physical device:  http://<your-pc-ip>:8000/api
  // Production:       https://farmmantra.duckdns.org/api
  //
  // To switch: change the value below, or use --dart-define=API_BASE_URL=...
  // from the command line when building.
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://farmmantra.duckdns.org/api',
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
