import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../services/api/api_service.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/theme/app_colors.dart';
import '../../../models/customer.dart';
import '../../../providers/customer_provider.dart';
import '../../../widgets/common/app_button.dart';
import '../../../widgets/common/app_card.dart';
import '../../../widgets/common/app_text_field.dart';
import '../../../widgets/common/error_view.dart';
import '../../../widgets/common/loading_view.dart';
import '../../../core/utils/validators.dart';

class CustomerListScreen extends StatefulWidget {
  const CustomerListScreen({super.key});

  @override
  State<CustomerListScreen> createState() => _CustomerListScreenState();
}

class _CustomerListScreenState extends State<CustomerListScreen> {
  final _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    final provider = context.read<CustomerProvider>();
    provider.loadCustomers(silent: provider.customers.isNotEmpty);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppConstants.defaultPadding,
            AppConstants.defaultPadding,
            AppConstants.defaultPadding,
            0,
          ),
          child: AppSearchField(
            controller: _searchController,
            hint: 'Search customers...',
            onChanged: (q) => setState(() => _searchQuery = q),
            onClear: () => setState(() => _searchQuery = ''),
          ),
        ),
        const SizedBox(height: AppConstants.smallPadding),
        Expanded(child: _buildCustomerList()),
      ],
    );
  }

  Widget _buildCustomerList() {
    return Consumer<CustomerProvider>(
      builder: (context, provider, _) {
        if (provider.isLoading) {
          return const LoadingView(message: 'Loading customers...');
        }

        if (provider.error != null) {
          return ErrorView(
            message: provider.error!,
            onRetry: () => provider.loadCustomers(),
          );
        }

        if (provider.customers.isEmpty) {
          return EmptyView(
            message: 'No customers yet. Add your first customer.',
            title: 'No Customers',
            icon: Icons.people_outline,
            actionLabel: 'Add Customer',
            onAction: () => _showAddCustomerSheet(context),
          );
        }

        final filtered = _searchQuery.isEmpty
            ? provider.customers
            : provider.customers
                .where((c) =>
                    c.name.toLowerCase().contains(_searchQuery.toLowerCase()) ||
                    c.phone.toLowerCase().contains(_searchQuery.toLowerCase()))
                .toList();

        return RefreshIndicator(
          onRefresh: () => provider.loadCustomers(),
          child: ListView.builder(
            padding: const EdgeInsets.only(
              bottom: 88,
            ),
            itemCount: filtered.length,
            itemBuilder: (context, index) =>
                _buildCustomerCard(filtered[index]),
          ),
        );
      },
    );
  }

  Widget _buildCustomerCard(Customer customer) {
    return AppCard(
      child: Row(
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: AppColors.primaryGreen.withAlpha(26),
            child: Text(
              customer.name.isNotEmpty ? customer.name[0].toUpperCase() : '?',
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 16,
                color: AppColors.primaryGreen,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  customer.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 14,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    const Icon(Icons.phone, size: 12, color: AppColors.textSecondary),
                    const SizedBox(width: 4),
                    Text(
                      '${customer.typeLabel} · ${customer.phone}${customer.districtName != null ? ' · ${customer.districtName}' : ''}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
                if (customer.location != null && customer.location!.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Row(
                    children: [
                      const Icon(Icons.location_on_outlined, size: 12, color: AppColors.textSecondary),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          customer.location!,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AppColors.textLight),
        ],
      ),
    );
  }

  void _showAddCustomerSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (_) => const _AddCustomerSheet(),
    );
  }
}

class _AddCustomerSheet extends StatefulWidget {
  const _AddCustomerSheet();

  @override
  State<_AddCustomerSheet> createState() => _AddCustomerSheetState();
}

class _AddCustomerSheetState extends State<_AddCustomerSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _locationController = TextEditingController();
  final _notesController = TextEditingController();
  bool _isSaving = false;
  String _customerType = 'farmer';
  String? _districtId;
  List<Map<String, dynamic>> _districts = [];

  @override
  void initState() {
    super.initState();
    _loadDistricts();
  }

  /// Only districts of the franchise's region can be chosen.
  Future<void> _loadDistricts() async {
    try {
      final list = await context.read<ApiService>().getDistricts();
      if (mounted) setState(() => _districts = list);
    } catch (_) {
      // District stays optional if the list cannot be loaded.
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _locationController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        AppConstants.defaultPadding,
        AppConstants.defaultPadding,
        AppConstants.defaultPadding,
        MediaQuery.of(context).viewInsets.bottom + AppConstants.defaultPadding,
      ),
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Add New Customer',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: _nameController,
                label: 'Customer Name',
                hint: 'Enter customer name',
                prefixIcon: Icons.person_outline,
                validator: (v) => Validators.required(v, 'Customer name'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _customerType,
                decoration: const InputDecoration(
                  labelText: 'Customer type',
                  prefixIcon: Icon(Icons.category_outlined),
                ),
                items: const [
                  DropdownMenuItem(value: 'farmer', child: Text('Farmer')),
                  DropdownMenuItem(value: 'retailer', child: Text('Retailer')),
                ],
                onChanged: (v) => setState(() => _customerType = v ?? 'farmer'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _districtId,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'District',
                  prefixIcon: Icon(Icons.map_outlined),
                ),
                items: _districts
                    .map(
                      (d) => DropdownMenuItem(
                        value: d['id']?.toString(),
                        child: Text(d['name']?.toString() ?? ''),
                      ),
                    )
                    .toList(),
                onChanged: (v) => setState(() => _districtId = v),
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _phoneController,
                label: 'Phone Number',
                hint: 'e.g. 0700123456',
                prefixIcon: Icons.phone_outlined,
                keyboardType: TextInputType.phone,
                validator: Validators.phone,
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _locationController,
                label: 'Location',
                hint: 'e.g. Kampala, Uganda',
                prefixIcon: Icons.location_on_outlined,
              ),
              const SizedBox(height: 12),
              AppTextField(
                controller: _notesController,
                label: 'Notes (Optional)',
                hint: 'Any additional notes',
                prefixIcon: Icons.notes_outlined,
                maxLines: 2,
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: AppButton(
                  label: 'Save Customer',
                  isLoading: _isSaving,
                  onPressed: _saveCustomer,
                  icon: Icons.person_add_outlined,
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _saveCustomer() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);

    try {
      final success = await context.read<CustomerProvider>().createCustomer({
        'name': _nameController.text.trim(),
        'customer_type': _customerType,
        if (_districtId != null) 'district_id': int.tryParse(_districtId!),
        'phone': _phoneController.text.trim(),
        'address': _locationController.text.trim().isEmpty
            ? null
            : _locationController.text.trim(),
        'location': _locationController.text.trim().isEmpty
            ? null
            : _locationController.text.trim(),
        'notes': _notesController.text.trim().isEmpty
            ? null
            : _notesController.text.trim(),
      });
      if (!mounted) return;

      if (success) {
        Navigator.of(context).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Customer added successfully'),
            backgroundColor: AppColors.primaryGreen,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              context.read<CustomerProvider>().error ??
                  'Failed to add customer. Please try again.',
            ),
            backgroundColor: AppColors.error,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
            ),
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to add customer: $e'),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }
}
