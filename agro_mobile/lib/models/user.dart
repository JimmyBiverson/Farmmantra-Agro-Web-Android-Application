import '../core/enums/user_role.dart';
import '../core/utils/image_url.dart';

class User {
  final String id;
  final String name;
  final String email;
  final String phone;
  final String? address;
  final String? gender;
  final UserRole role;
  final String? franchiseId;
  final String? franchiseName;
  final String? franchiseCode;
  final String? avatarUrl;
  final DateTime? createdAt;
  final bool isActive;
  final Map<String, dynamic> notificationPreferences;

  /// Franchise partners must accept the terms & conditions on first login
  /// (and again whenever a new version is published).
  final bool needsTermsAcceptance;
  final String? franchiseRegion;

  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    this.address,
    this.gender,
    required this.role,
    this.franchiseId,
    this.franchiseName,
    this.franchiseCode,
    this.avatarUrl,
    this.createdAt,
    this.isActive = true,
    this.notificationPreferences = const {},
    this.needsTermsAcceptance = false,
    this.franchiseRegion,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    final rawAvatar = json['avatar_url'] ?? json['avatar'];
    final resolvedAvatar = resolveImageUrl(rawAvatar);
    return User(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'] ?? '',
      address: json['address'],
      gender: json['gender'],
      role: UserRole.values.firstWhere(
        (r) => r.name == json['role'],
        orElse: () => UserRole.franchisePartner,
      ),
      franchiseId: json['franchise_id']?.toString(),
      franchiseName: json['franchise_name'],
      franchiseCode: json['franchise_code'],
      avatarUrl: resolvedAvatar.isEmpty ? null : resolvedAvatar,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'])
          : null,
      isActive: json['is_active'] ?? true,
      notificationPreferences: json['notification_preferences'] is Map
          ? Map<String, dynamic>.from(json['notification_preferences'])
          : const {},
      needsTermsAcceptance: json['needs_terms_acceptance'] == true,
      franchiseRegion: json['franchise_region']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'address': address,
      'gender': gender,
      'role': role.name,
      'franchise_id': franchiseId,
      'franchise_name': franchiseName,
      'franchise_code': franchiseCode,
      'avatar_url': avatarUrl,
      'created_at': createdAt?.toIso8601String(),
      'is_active': isActive,
      'needs_terms_acceptance': needsTermsAcceptance,
      'franchise_region': franchiseRegion,
    };
  }

  User copyWith({
    String? name,
    String? email,
    String? phone,
    String? address,
    String? gender,
    String? avatarUrl,
    bool? isActive,
    Map<String, dynamic>? notificationPreferences,
    bool? needsTermsAcceptance,
  }) {
    return User(
      id: id,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      address: address ?? this.address,
      gender: gender ?? this.gender,
      role: role,
      franchiseId: franchiseId,
      franchiseName: franchiseName,
      franchiseCode: franchiseCode,
      avatarUrl: avatarUrl ?? this.avatarUrl,
      createdAt: createdAt,
      isActive: isActive ?? this.isActive,
      notificationPreferences:
          notificationPreferences ?? this.notificationPreferences,
      needsTermsAcceptance: needsTermsAcceptance ?? this.needsTermsAcceptance,
      franchiseRegion: franchiseRegion,
    );
  }

  bool get isNotificationEnabled =>
      notificationPreferences['notifications_enabled'] != false;

  bool get wantsOrderNotifications =>
      notificationPreferences['orders'] != false;

  bool get wantsPaymentNotifications =>
      notificationPreferences['payments'] != false;

  bool get wantsDeliveryNotifications =>
      notificationPreferences['deliveries'] != false;

  String get initials {
    final parts = name.split(' ');
    if (parts.length >= 2) {
      return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
    }
    return name.isNotEmpty ? name[0].toUpperCase() : '?';
  }
}
