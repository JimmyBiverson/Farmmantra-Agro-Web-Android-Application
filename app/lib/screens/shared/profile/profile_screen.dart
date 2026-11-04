import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/theme/app_colors.dart';
import '../../../models/user.dart';
import '../../../providers/auth_provider.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/logout_dialog.dart';
import '../support/chat_screen.dart';

class ProfileScreen extends StatefulWidget {
  final bool showAppBar;

  const ProfileScreen({super.key, this.showAppBar = true});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _isUploadingAvatar = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final auth = context.read<AuthProvider>();
      auth.refreshProfile();
    });
  }

  Future<void> _pickAndUploadAvatar() async {
    final auth = context.read<AuthProvider>();
    final picker = ImagePicker();
    final file = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 512,
      maxHeight: 512,
      imageQuality: 85,
    );
    if (file == null || !mounted) return;

    setState(() => _isUploadingAvatar = true);
    final bytes = await file.readAsBytes();
    final success = await auth.uploadAvatar(bytes, file.name);
    if (!mounted) return;
    setState(() => _isUploadingAvatar = false);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          success ? 'Profile photo updated' : auth.error ?? 'Upload failed',
        ),
        backgroundColor: success ? AppColors.primaryGreen : AppColors.error,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _showEditInfoDialog(User user) {
    final nameController = TextEditingController(text: user.name);
    final phoneController = TextEditingController(text: user.phone);
    final addressController = TextEditingController(text: user.address ?? '');
    final formKey = GlobalKey<FormState>();

    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Edit Profile'),
        content: Form(
          key: formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: nameController,
                  decoration: const InputDecoration(labelText: 'Full Name'),
                  textInputAction: TextInputAction.next,
                  validator: (value) => (value == null || value.trim().isEmpty)
                      ? 'Name is required'
                      : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: phoneController,
                  decoration: const InputDecoration(labelText: 'Phone'),
                  keyboardType: TextInputType.phone,
                  textInputAction: TextInputAction.next,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: addressController,
                  decoration: const InputDecoration(labelText: 'Address'),
                  textInputAction: TextInputAction.done,
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () async {
              if (!formKey.currentState!.validate()) return;
              final auth = context.read<AuthProvider>();
              final success = await auth.updateProfile({
                'name': nameController.text.trim(),
                'phone': phoneController.text.trim(),
                'address': addressController.text.trim(),
              });
              if (dialogContext.mounted) Navigator.of(dialogContext).pop();
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      success
                          ? 'Profile updated'
                          : auth.error ?? 'Update failed',
                    ),
                    backgroundColor: success
                        ? AppColors.primaryGreen
                        : AppColors.error,
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              }
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }

  void _showChangePasswordDialog() {
    final currentController = TextEditingController();
    final newController = TextEditingController();
    final confirmController = TextEditingController();
    final formKey = GlobalKey<FormState>();

    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Change Password'),
        content: Form(
          key: formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: currentController,
                  decoration: const InputDecoration(
                    labelText: 'Current Password',
                  ),
                  obscureText: true,
                  textInputAction: TextInputAction.next,
                  validator: (value) => (value == null || value.isEmpty)
                      ? 'Enter current password'
                      : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: newController,
                  decoration: const InputDecoration(labelText: 'New Password'),
                  obscureText: true,
                  textInputAction: TextInputAction.next,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Enter new password';
                    }
                    if (value.length < 6) return 'At least 6 characters';
                    return null;
                  },
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: confirmController,
                  decoration: const InputDecoration(
                    labelText: 'Confirm New Password',
                  ),
                  obscureText: true,
                  textInputAction: TextInputAction.done,
                  validator: (value) => value != newController.text
                      ? 'Passwords do not match'
                      : null,
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () async {
              if (!formKey.currentState!.validate()) return;
              final auth = context.read<AuthProvider>();
              final success = await auth.changePassword(
                currentController.text,
                newController.text,
              );
              if (dialogContext.mounted) Navigator.of(dialogContext).pop();
              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      success
                          ? 'Password changed'
                          : auth.error ?? 'Change failed',
                    ),
                    backgroundColor: success
                        ? AppColors.primaryGreen
                        : AppColors.error,
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              }
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }

  Future<void> _togglePreference(
    AuthProvider auth,
    User user,
    String key,
    bool value,
  ) async {
    final prefs = Map<String, dynamic>.from(user.notificationPreferences);
    prefs[key] = value;
    await auth.updateProfile({'notification_preferences': prefs});
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final content = SingleChildScrollView(
      padding: const EdgeInsets.all(AppConstants.defaultPadding),
      child: Column(
        children: [
          _buildHeader(user),
          const SizedBox(height: 16),
          _buildContactCard(user),
          const SizedBox(height: 16),
          _buildNotificationPreferences(user),
          const SizedBox(height: 16),
          _buildFranchiseCard(user),
          const SizedBox(height: 16),
          _buildAccountActions(context),
          const SizedBox(height: 24),
        ],
      ),
    );

    return Scaffold(
      appBar: widget.showAppBar
          ? AppBar(title: const Text('My Profile'))
          : null,
      body: content,
    );
  }

  Widget _buildHeader(User? user) {
    final avatarUrl = user?.avatarUrl;
    final hasAvatar = avatarUrl != null && avatarUrl.isNotEmpty;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primaryGreen, AppColors.primaryGreenDark],
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              CircleAvatar(
                radius: 40,
                backgroundColor: Colors.white.withAlpha(51),
                backgroundImage: hasAvatar ? NetworkImage(avatarUrl) : null,
                child: hasAvatar
                    ? null
                    : Text(
                        user?.initials ?? '?',
                        style: const TextStyle(
                          fontSize: 26,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
              ),
              Positioned(
                right: 0,
                bottom: 0,
                child: GestureDetector(
                  onTap: _isUploadingAvatar ? null : _pickAndUploadAvatar,
                  child: Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withAlpha(38),
                          blurRadius: 4,
                        ),
                      ],
                    ),
                    child: _isUploadingAvatar
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: AppColors.primaryGreen,
                            ),
                          )
                        : const Icon(
                            Icons.photo_camera,
                            size: 16,
                            color: AppColors.primaryGreen,
                          ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            user?.name ?? '',
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 4),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white.withAlpha(38),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              user?.role.displayName ?? '',
              style: const TextStyle(fontSize: 12, color: Colors.white),
            ),
          ),
          if (user?.franchiseName != null &&
              user!.franchiseName!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              user.franchiseName!,
              style: const TextStyle(fontSize: 12, color: Colors.white70),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildContactCard(User? user) {
    final phone = user?.phone;
    final address = user?.address;
    final gender = user?.gender;
    final rows = <Widget>[
      _buildInfoRow(Icons.email_outlined, 'Email', user?.email ?? ''),
    ];
    if (phone != null && phone.isNotEmpty) {
      rows.add(_buildInfoRow(Icons.phone_outlined, 'Phone', phone));
    }
    if (address != null && address.isNotEmpty) {
      rows.add(_buildInfoRow(Icons.location_on_outlined, 'Address', address));
    }
    if (gender != null && gender.isNotEmpty) {
      rows.add(_buildInfoRow(Icons.person_outline, 'Gender', gender));
    }
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Contact Information',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
              if (user != null)
                IconButton(
                  onPressed: () => _showEditInfoDialog(user),
                  icon: const Icon(Icons.edit_outlined, size: 18),
                  color: AppColors.primaryGreen,
                  tooltip: 'Edit profile',
                ),
            ],
          ),
          const SizedBox(height: 8),
          ...rows,
        ],
      ),
    );
  }

  Widget _buildNotificationPreferences(User? user) {
    if (user == null) return const SizedBox.shrink();
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(
                Icons.notifications_outlined,
                size: 18,
                color: AppColors.primaryGreen,
              ),
              SizedBox(width: 8),
              Text(
                'Notification Preferences',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            title: const Text(
              'Enable notifications',
              style: TextStyle(fontSize: 13),
            ),
            value: user.isNotificationEnabled,
            activeTrackColor: AppColors.primaryGreen,
            onChanged: (value) => _togglePreference(
              context.read<AuthProvider>(),
              user,
              'notifications_enabled',
              value,
            ),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            title: const Text('Order updates', style: TextStyle(fontSize: 13)),
            value: user.wantsOrderNotifications && user.isNotificationEnabled,
            activeTrackColor: AppColors.primaryGreen,
            onChanged: user.isNotificationEnabled
                ? (value) => _togglePreference(
                    context.read<AuthProvider>(),
                    user,
                    'orders',
                    value,
                  )
                : null,
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            title: const Text(
              'Payment updates',
              style: TextStyle(fontSize: 13),
            ),
            value: user.wantsPaymentNotifications && user.isNotificationEnabled,
            activeTrackColor: AppColors.primaryGreen,
            onChanged: user.isNotificationEnabled
                ? (value) => _togglePreference(
                    context.read<AuthProvider>(),
                    user,
                    'payments',
                    value,
                  )
                : null,
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            dense: true,
            title: const Text(
              'Delivery updates',
              style: TextStyle(fontSize: 13),
            ),
            value:
                user.wantsDeliveryNotifications && user.isNotificationEnabled,
            activeTrackColor: AppColors.primaryGreen,
            onChanged: user.isNotificationEnabled
                ? (value) => _togglePreference(
                    context.read<AuthProvider>(),
                    user,
                    'deliveries',
                    value,
                  )
                : null,
          ),
        ],
      ),
    );
  }

  Widget _buildFranchiseCard(User? user) {
    final franchiseName = user?.franchiseName;
    final franchiseCode = user?.franchiseCode;
    final franchiseId = user?.franchiseId;
    final hasFranchise = franchiseName != null && franchiseName.isNotEmpty;
    if (!hasFranchise) return const SizedBox.shrink();
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Franchise',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 8),
          _buildInfoRow(Icons.storefront_outlined, 'Name', franchiseName),
          if (franchiseCode != null && franchiseCode.isNotEmpty)
            _buildInfoRow(Icons.tag_outlined, 'Code', franchiseCode),
          if (franchiseId != null && franchiseId.isNotEmpty)
            _buildInfoRow(Icons.numbers_outlined, 'ID', franchiseId),
        ],
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: AppColors.primaryGreen.withAlpha(26),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, size: 18, color: AppColors.primaryGreen),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppColors.textLight,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 14,
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAccountActions(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: () {
              context.read<AuthProvider>().refreshProfile();
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Profile refreshed'),
                    backgroundColor: AppColors.primaryGreen,
                    behavior: SnackBarBehavior.floating,
                  ),
                );
              }
            },
            icon: const Icon(Icons.refresh, color: AppColors.primaryGreen),
            label: const Text(
              'Refresh Profile',
              style: TextStyle(color: AppColors.primaryGreen),
            ),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: AppColors.primaryGreen),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: () {
              Navigator.of(
                context,
              ).push(MaterialPageRoute(builder: (_) => const ChatScreen()));
            },
            icon: const Icon(Icons.help_outline, color: AppColors.primaryGreen),
            label: const Text(
              'Help & Support',
              style: TextStyle(color: AppColors.primaryGreen),
            ),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: AppColors.primaryGreen),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: _showChangePasswordDialog,
            icon: const Icon(Icons.lock_outline, color: AppColors.primaryGreen),
            label: const Text(
              'Change Password',
              style: TextStyle(color: AppColors.primaryGreen),
            ),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: AppColors.primaryGreen),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: () async {
              final confirmed = await confirmLogout(context);
              if (!confirmed || !context.mounted) return;
              context.read<AuthProvider>().logout();
              GoRouter.of(context).go('/login');
            },
            icon: const Icon(Icons.logout, color: AppColors.error),
            label: const Text(
              'Logout',
              style: TextStyle(color: AppColors.error),
            ),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: AppColors.error),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
          ),
        ),
      ],
    );
  }
}
