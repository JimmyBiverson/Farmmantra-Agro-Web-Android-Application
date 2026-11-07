import '../../../core/utils/formatters.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../../providers/auth_provider.dart';
import '../../../core/enums/user_role.dart';
import '../../../core/utils/image_url.dart';
import '../data/operations_api.dart';
import 'workspace_ui.dart';

class KnowledgeScreen extends StatefulWidget {
  final bool embedded;
  const KnowledgeScreen({super.key, this.embedded = false});
  @override
  State<KnowledgeScreen> createState() => _KnowledgeScreenState();
}

class _KnowledgeScreenState extends State<KnowledgeScreen> {
  String kind = 'crop', search = '', crop = '', category = '';
  final searchController = TextEditingController();
  final listKey = GlobalKey<RemoteRecordsState>();
  List<Map<String, dynamic>> facets = [];
  String? facetError;
  bool facetsLoading = true;
  @override
  void initState() {
    super.initState();
    loadFacets();
  }

  Future<void> loadFacets() async {
    final api = operationsApi(context);
    try {
      final response = await api.get('operations/knowledge/facets');
      if (mounted) {
        setState(() {
          facets = OperationsApi.rows(response);
          facetError = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => facetError = operationError(e));
    } finally {
      api.dispose();
      if (mounted) setState(() => facetsLoading = false);
    }
  }

  Widget hierarchy() {
    if (facetsLoading) return const Center(child: CircularProgressIndicator());
    if (facetError != null) {
      return Center(
        child: TextButton(
          onPressed: loadFacets,
          child: Text('$facetError · Retry'),
        ),
      );
    }
    final selected = facets.where(
      (r) => r['kind'] == kind && (crop.isEmpty || r['crop'] == crop),
    );
    final choices = <String, String>{};
    for (final r in selected) {
      if (kind == 'crop' && crop.isEmpty) {
        if (r['crop'] != null) choices[r['crop']] = r['crop'];
      } else {
        choices[r['category_id'].toString()] = r['category_name'] ?? 'Category';
      }
    }
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        if (choices.isEmpty)
          const WorkspaceEmpty(
            title: 'Your library is being prepared',
            message:
                'Approved crop advice and product photographs will appear when published by Farmmantra.',
            icon: Icons.menu_book_outlined,
          ),
        for (final e in choices.entries)
          WorkspaceTile(
            title: e.value,
            subtitle: kind == 'crop' && crop.isEmpty
                ? 'Explore crop categories'
                : 'Explore issues and recommendations',
            icon: kind == 'crop' && crop.isEmpty
                ? Icons.grass
                : Icons.category_outlined,
            onTap: () => setState(() {
              if (kind == 'crop' && crop.isEmpty) {
                crop = e.key;
              } else {
                category = e.key;
              }
            }),
          ),
      ],
    );
  }

  @override
  void dispose() {
    searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final admin =
        context.watch<AuthProvider>().userRole == UserRole.systemAdministrator;
    final child = Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'THE GROWER’S LIBRARY',
                style: TextStyle(
                  fontSize: 11,
                  letterSpacing: 1.6,
                  fontWeight: FontWeight.w800,
                  color: workspaceGreen,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Knowledge that grows with you',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 16),
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(
                    value: 'crop',
                    label: Text('Crop knowledge'),
                    icon: Icon(Icons.grass),
                  ),
                  ButtonSegment(
                    value: 'product',
                    label: Text('Products'),
                    icon: Icon(Icons.science_outlined),
                  ),
                ],
                selected: {kind},
                onSelectionChanged: (v) => setState(() {
                  kind = v.first;
                  crop = '';
                  category = '';
                }),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: searchController,
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  hintText: kind == 'crop'
                      ? 'Search a crop, pest or disease'
                      : 'Search product knowledge',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: IconButton(
                    tooltip: 'Search',
                    icon: const Icon(Icons.arrow_forward),
                    onPressed: () =>
                        setState(() => search = searchController.text.trim()),
                  ),
                ),
                onSubmitted: (v) => setState(() => search = v.trim()),
              ),
              if (crop.isNotEmpty || category.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Wrap(
                    spacing: 8,
                    children: [
                      if (crop.isNotEmpty)
                        InputChip(
                          label: Text(crop),
                          onDeleted: () => setState(() {
                            crop = '';
                            category = '';
                          }),
                        ),
                      if (category.isNotEmpty)
                        InputChip(
                          label: const Text('Category selected'),
                          onDeleted: () => setState(() => category = ''),
                        ),
                    ],
                  ),
                ),
              if (kind == 'product')
                TextButton.icon(
                  onPressed: () =>
                      openWorkspace(context, const ProductReferenceScreen()),
                  icon: const Icon(Icons.inventory_2_outlined),
                  label: const Text('Browse all products & actual photographs'),
                ),
              if (admin)
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: () async {
                      await Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const KnowledgeEditor(),
                        ),
                      );
                      listKey.currentState?.reload();
                      loadFacets();
                    },
                    icon: const Icon(Icons.add),
                    label: const Text('Add client-approved content'),
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child:
              search.isEmpty &&
                  ((kind == 'crop' && crop.isEmpty) || category.isEmpty)
              ? hierarchy()
              : RemoteRecords(
                  key: listKey,
                  path:
                      'operations/knowledge?kind=$kind&search=${Uri.encodeQueryComponent(search)}${crop.isEmpty ? '' : '&crop=${Uri.encodeQueryComponent(crop)}'}${category.isEmpty ? '' : '&category_id=$category'}',
                  emptyTitle: 'Your library is being prepared',
                  emptyMessage:
                      'Approved crop advice, dosage guidance and product photographs will appear here when published by Farmmantra.',
                  builder: (context, r, reload) => Card(
                    margin: const EdgeInsets.only(bottom: 16),
                    clipBehavior: Clip.antiAlias,
                    elevation: 0,
                    child: InkWell(
                      onTap: () =>
                          openWorkspace(context, KnowledgeDetail(article: r)),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (r['image_url'] != null)
                            Image.network(
                              resolveImageUrl(r['image_url']),
                              height: 160,
                              width: double.infinity,
                              fit: BoxFit.cover,
                              errorBuilder: (_, error, stack) => const SizedBox(
                                height: 100,
                                child: Center(
                                  child: Icon(Icons.grass, size: 40),
                                ),
                              ),
                            ),
                          Padding(
                            padding: const EdgeInsets.all(18),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Wrap(
                                  spacing: 8,
                                  children: [
                                    if (r['crop'] != null)
                                      ActionChip(
                                        label: Text(r['crop']),
                                        onPressed: () =>
                                            setState(() => crop = r['crop']),
                                      ),
                                    ActionChip(
                                      label: Text(
                                        r['category']?['name'] ?? 'Product',
                                      ),
                                      onPressed: () => setState(
                                        () => category = r['category_id']
                                            .toString(),
                                      ),
                                    ),
                                    if (r['published'] != true)
                                      const StatusPill('draft'),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  r['title'],
                                  style: const TextStyle(
                                    fontSize: 20,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                if (r['product'] != null)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 8),
                                    child: Text(
                                      'Recommended: ${r['product']['name']}',
                                    ),
                                  ),
                                const SizedBox(height: 12),
                                Row(
                                  children: [
                                    const Text(
                                      'Read guidance',
                                      style: TextStyle(
                                        color: workspaceGreen,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    const Spacer(),
                                    if (admin)
                                      IconButton(
                                        tooltip: 'Edit article',
                                        icon: const Icon(Icons.edit_outlined),
                                        onPressed: () async {
                                          await Navigator.push(
                                            context,
                                            MaterialPageRoute(
                                              builder: (_) =>
                                                  KnowledgeEditor(article: r),
                                            ),
                                          );
                                          reload();
                                        },
                                      ),
                                    const Icon(Icons.arrow_forward, size: 20),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
        ),
      ],
    );
    return widget.embedded
        ? ColoredBox(color: workspaceBackground, child: child)
        : WorkspacePage(title: 'Knowledge', child: child);
  }
}

class KnowledgeDetail extends StatelessWidget {
  final Map<String, dynamic> article;
  const KnowledgeDetail({super.key, required this.article});
  @override
  Widget build(BuildContext context) {
    final content = Map<String, dynamic>.from(article['content'] ?? {});
    return WorkspacePage(
      title: article['crop'] ?? 'Product knowledge',
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          if (article['image_url'] != null)
            ClipRRect(
              borderRadius: BorderRadius.circular(22),
              child: Image.network(
                resolveImageUrl(article['image_url']),
                height: 220,
                fit: BoxFit.cover,
                errorBuilder: (_, error, stack) => const SizedBox.shrink(),
              ),
            ),
          const SizedBox(height: 20),
          Text(
            article['title'],
            style: Theme.of(
              context,
            ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
          if (article['product'] != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 14),
              child: Text(
                'Product: ${article['product']['name']}',
                style: const TextStyle(
                  fontSize: 18,
                  color: workspaceGreen,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          for (final field in {
            'active_ingredient': 'Active ingredient',
            'dosage': 'Dosage',
            'stage': 'When to use',
            'method': 'How to use',
            'dos': 'Do’s',
            'donts': 'Don’ts',
            'phi': 'Pre-harvest interval (PHI)',
            'notes': 'Additional guidance',
          }.entries)
            if ((content[field.key] ?? '').toString().isNotEmpty)
              Card(
                margin: const EdgeInsets.only(bottom: 12),
                elevation: 0,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        field.value,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          color: workspaceGreen,
                        ),
                      ),
                      const SizedBox(height: 8),
                      SelectableText(
                        content[field.key].toString(),
                        style: const TextStyle(height: 1.6),
                      ),
                    ],
                  ),
                ),
              ),
        ],
      ),
    );
  }
}

class KnowledgeEditor extends StatefulWidget {
  final Map<String, dynamic>? article;
  const KnowledgeEditor({super.key, this.article});
  @override
  State<KnowledgeEditor> createState() => _KnowledgeEditorState();
}

class _KnowledgeEditorState extends State<KnowledgeEditor> {
  final key = GlobalKey<FormState>();
  final title = TextEditingController(), crop = TextEditingController();
  final fields = {
    for (final name in [
      'active_ingredient',
      'dosage',
      'stage',
      'method',
      'dos',
      'donts',
      'phi',
      'notes',
    ])
      name: TextEditingController(),
  };
  String kind = 'crop';
  String? category, product;
  bool published = false, saving = false;
  List<Map<String, dynamic>> categories = [], products = [];
  OperationsFile? image;
  String? error;
  @override
  void initState() {
    super.initState();
    final r = widget.article;
    if (r != null) {
      title.text = r['title'];
      crop.text = r['crop'] ?? '';
      kind = r['kind'];
      category = r['category_id'].toString();
      product = r['product_id']?.toString();
      published = r['published'] == true;
      for (final entry in fields.entries) {
        entry.value.text = r['content']?[entry.key] ?? '';
      }
    }
    _load();
  }

  Future<void> _load() async {
    final api = operationsApi(context);
    try {
      final result = await api.get('operations/options');
      if (mounted) {
        setState(() {
          categories = List<Map<String, dynamic>>.from(
            result['data']['categories'],
          );
          products = List<Map<String, dynamic>>.from(
            result['data']['products'],
          );
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = operationError(e));
    } finally {
      api.dispose();
    }
  }

  @override
  void dispose() {
    title.dispose();
    crop.dispose();
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Knowledge editor',
    child: Form(
      key: key,
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          const Text(
            'Publish only client-approved guidance and actual product photographs.',
            style: TextStyle(height: 1.5),
          ),
          if (error != null) Text(error!),
          const SizedBox(height: 20),
          DropdownButtonFormField(
            initialValue: kind,
            decoration: const InputDecoration(labelText: 'Library'),
            items: const [
              DropdownMenuItem(value: 'crop', child: Text('Crop knowledge')),
              DropdownMenuItem(
                value: 'product',
                child: Text('Product knowledge'),
              ),
            ],
            onChanged: (v) => setState(() => kind = v!),
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: title,
            decoration: const InputDecoration(
              labelText: 'Disease / pest / product title',
            ),
            validator: (v) => (v?.isEmpty ?? true) ? 'Enter a title' : null,
          ),
          if (kind == 'crop')
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: TextFormField(
                controller: crop,
                decoration: const InputDecoration(labelText: 'Crop name'),
                validator: (v) => (v?.isEmpty ?? true) ? 'Enter a crop' : null,
              ),
            ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            key: ValueKey('category-${categories.length}'),
            initialValue: categories.any((r) => r['id'].toString() == category)
                ? category
                : null,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Category'),
            items: categories
                .map(
                  (r) => DropdownMenuItem(
                    value: r['id'].toString(),
                    child: Text(r['name']),
                  ),
                )
                .toList(),
            onChanged: (v) => category = v,
            validator: (v) => v == null ? 'Choose a category' : null,
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            key: ValueKey('product-${products.length}'),
            initialValue: products.any((r) => r['id'].toString() == product)
                ? product
                : null,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Recommended product'),
            items: products
                .map(
                  (r) => DropdownMenuItem(
                    value: r['id'].toString(),
                    child: Text(r['name'], overflow: TextOverflow.ellipsis),
                  ),
                )
                .toList(),
            onChanged: (v) => product = v,
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: () async {
              final f = await ImagePicker().pickImage(
                source: ImageSource.gallery,
                maxWidth: 1800,
                imageQuality: 85,
              );
              if (f != null) {
                final bytes = await f.readAsBytes();
                if (mounted) {
                  setState(() => image = OperationsFile(f.name, bytes));
                }
              }
            },
            icon: const Icon(Icons.add_photo_alternate_outlined),
            label: Text(image?.name ?? 'Add actual photograph'),
          ),
          for (final entry in fields.entries)
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: TextFormField(
                controller: entry.value,
                maxLines: 3,
                decoration: InputDecoration(
                  labelText: {
                    'active_ingredient': 'Active ingredient',
                    'dosage': 'Dosage',
                    'stage': 'When / crop stage',
                    'method': 'Application method',
                    'dos': 'Do’s',
                    'donts': 'Don’ts',
                    'phi': 'PHI',
                    'notes': 'Other information',
                  }[entry.key],
                ),
                validator: (v) =>
                    published && entry.key != 'notes' && (v?.isEmpty ?? true)
                    ? 'Required before publication'
                    : null,
              ),
            ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Publish approved guidance'),
            subtitle: const Text('Drafts are visible only to Admin.'),
            value: published,
            onChanged: (v) => setState(() => published = v),
          ),
          FilledButton(
            onPressed: saving ? null : _save,
            child: Text(saving ? 'Saving…' : 'Save guidance'),
          ),
          const SizedBox(height: 24),
        ],
      ),
    ),
  );
  Future<void> _save() async {
    if (!key.currentState!.validate()) return;
    setState(() => saving = true);
    final api = operationsApi(context);
    try {
      await api.upload(
        'operations/knowledge${widget.article == null ? '' : '/${widget.article!['id']}'}',
        {
          'kind': kind,
          'crop': crop.text,
          'title': title.text,
          'category_id': category,
          'product_id': product,
          'published': published,
          'content': {for (final e in fields.entries) e.key: e.value.text},
        },
        files: {
          if (image != null) 'image': [image!],
        },
      );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) showOperationError(context, e);
    } finally {
      api.dispose();
      if (mounted) setState(() => saving = false);
    }
  }
}

/// Read-only catalogue for every role; ordering stays in the Franchise area.
class ProductReferenceScreen extends StatefulWidget {
  const ProductReferenceScreen({super.key});
  @override
  State<ProductReferenceScreen> createState() => _ProductReferenceScreenState();
}

class _ProductReferenceScreenState extends State<ProductReferenceScreen> {
  String search = '';
  String category = '';
  List<Map<String, dynamic>> categories = [];
  final controller = TextEditingController();
  @override
  void initState() {
    super.initState();
    loadCategories();
  }

  Future<void> loadCategories() async {
    final api = operationsApi(context);
    try {
      final result = await api.get('categories');
      if (mounted) {
        setState(() => categories = OperationsApi.rows(result));
      }
    } catch (e) {
      if (mounted) {
        showOperationError(context, e);
      }
    } finally {
      api.dispose();
    }
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => WorkspacePage(
    title: 'Product knowledge',
    child: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(20),
          child: TextField(
            controller: controller,
            decoration: const InputDecoration(
              labelText: 'Find a product by name or code',
              prefixIcon: Icon(Icons.search),
            ),
            textInputAction: TextInputAction.search,
            onSubmitted: (value) => setState(() => search = value.trim()),
          ),
        ),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            children: [
              for (final entry in <String, String>{
                '': 'All products',
                for (final r in categories) r['id'].toString(): r['name'],
              }.entries)
                Padding(
                  padding: const EdgeInsets.only(right: 8, bottom: 12),
                  child: ChoiceChip(
                    label: Text(entry.value),
                    selected: category == entry.key,
                    onSelected: (_) => setState(() => category = entry.key),
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: RemoteRecords(
            path:
                'products?search=${Uri.encodeQueryComponent(search)}${category.isEmpty ? '' : '&category_id=$category'}',
            emptyTitle: 'No matching products',
            emptyMessage: 'Try a different product name or code.',
            builder: (context, r, reload) => Card(
              elevation: 0,
              margin: const EdgeInsets.only(bottom: 16),
              clipBehavior: Clip.antiAlias,
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (r['image_url'] != null)
                      Image.network(
                        resolveImageUrl(r['image_url']),
                        height: 170,
                        width: double.infinity,
                        fit: BoxFit.contain,
                        errorBuilder: (_, error, stack) => const SizedBox(
                          height: 100,
                          child: Center(
                            child: Icon(Icons.inventory_2_outlined, size: 40),
                          ),
                        ),
                      ),
                    const SizedBox(height: 12),
                    Text(
                      r['category']?['name'] ?? 'Product',
                      style: const TextStyle(
                        fontSize: 11,
                        letterSpacing: 1,
                        fontWeight: FontWeight.w800,
                        color: workspaceGreen,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      r['name'],
                      style: const TextStyle(
                        fontSize: 23,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    Text('Code: ${r['sku']} · ${r['unit_of_measure'] ?? ''}'),
                    const Divider(height: 26),
                    Text(
                      'Farmer: ${Formatters.currency(r['farmer_price'] ?? r['farmer_unit_price'])}',
                    ),
                    Text(
                      'Retailer: ${Formatters.currency(r['selling_price'])}',
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Batch: ${r['batch_number'] ?? 'Not supplied'}\nManufactured: ${r['manufactured_at'] ?? 'Not supplied'}\nExpiry: ${r['expires_at'] ?? 'Not supplied'}',
                    ),
                    if (r['active_ingredient'] != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: Text(
                          'Active ingredient: ${r['active_ingredient']}',
                        ),
                      ),
                    if (r['description'] != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: Text(
                          r['description'],
                          style: const TextStyle(height: 1.5),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    ),
  );
}
