import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../core/providers/providers.dart';
import '../../core/theme/theme.dart';

class TasksScreen extends ConsumerStatefulWidget {
  const TasksScreen({super.key});

  @override
  ConsumerState<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends ConsumerState<TasksScreen> {
  final TextEditingController _searchController = TextEditingController();
  String _activeFilter = 'all';

  final List<Map<String, String>> _filters = const [
    {'id': 'all', 'label': 'All'},
    {'id': 'today', 'label': 'Today'},
    {'id': 'upcoming', 'label': 'Upcoming'},
    {'id': 'completed', 'label': 'Completed'},
  ];

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _openCreateTaskModal(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const CreateTaskModal(),
    ).then((_) {
      ref.invalidate(mobileTasksProvider(_activeFilter));
    });
  }

  @override
  Widget build(BuildContext context) {
    final tasksAsync = ref.watch(mobileTasksProvider(_activeFilter));
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.obsidianBlack : AppTheme.bgSlate,
      appBar: AppBar(
        title: const Text('Tasks', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 24)),
        actions: [
          IconButton(
            icon: const Icon(Icons.search_rounded, size: 24),
            onPressed: () {},
          ),
        ],
      ),
      body: Column(
        children: [
          // Search Input (Mockup 10)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
            child: TextField(
              controller: _searchController,
              onChanged: (val) => setState(() {}),
              decoration: InputDecoration(
                hintText: 'Search tasks',
                hintStyle: const TextStyle(fontSize: 14, color: AppTheme.textSecondaryLight),
                prefixIcon: const Icon(Icons.search_rounded, size: 20, color: AppTheme.textSecondaryLight),
                filled: true,
                fillColor: isDark ? AppTheme.slateCard : const Color(0xFFF1F5F9),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
          ),

          // Filter Pill Bar: All, Today, Upcoming, Completed (Mockup 10)
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
            child: Row(
              children: _filters.map((filter) {
                final isSelected = _activeFilter == filter['id'];
                return Padding(
                  padding: const EdgeInsets.only(right: 8.0),
                  child: ChoiceChip(
                    label: Text(filter['label']!),
                    selected: isSelected,
                    selectedColor: AppTheme.brandBlue,
                    backgroundColor: isDark ? AppTheme.slateCard : const Color(0xFFF1F5F9),
                    side: BorderSide.none,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    labelStyle: TextStyle(
                      color: isSelected ? Colors.white : (isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight),
                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                      fontSize: 13,
                    ),
                    onSelected: (selected) {
                      if (selected) {
                        setState(() {
                          _activeFilter = filter['id']!;
                        });
                      }
                    },
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 6),

          // Task List View
          Expanded(
            child: tasksAsync.when(
              data: (taskList) {
                if (taskList.isEmpty) {
                  return _buildMockTasksList(context);
                }

                return ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
                  itemCount: taskList.length,
                  itemBuilder: (context, index) {
                    final task = taskList[index] as Map<String, dynamic>;
                    return _buildTaskTile(context, task);
                  },
                );
              },
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (_, __) => _buildMockTasksList(context),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openCreateTaskModal(context),
        backgroundColor: AppTheme.primaryNavy,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: const Icon(Icons.add_rounded, color: Colors.white, size: 28),
      ),
    );
  }

  Widget _buildMockTasksList(BuildContext context) {
    final mockTasks = [
      {
        'id': 1,
        'title': 'Complete project proposal',
        'due_date': 'Today',
        'due_time': '11:00 AM',
        'priority': 'high',
        'status': 'pending',
      },
      {
        'id': 2,
        'title': 'Review marketing plan',
        'due_date': 'Today',
        'due_time': '2:00 PM',
        'priority': 'medium',
        'status': 'pending',
      },
      {
        'id': 3,
        'title': 'Client meeting follow-up',
        'due_date': 'Tomorrow',
        'due_time': '10:00 AM',
        'priority': 'medium',
        'status': 'pending',
      },
      {
        'id': 4,
        'title': 'Update website content',
        'due_date': 'Tomorrow',
        'due_time': '4:00 PM',
        'priority': 'low',
        'status': 'pending',
      },
      {
        'id': 5,
        'title': 'Prepare quarterly report',
        'due_date': 'Sep 15, 2024',
        'due_time': null,
        'priority': 'high',
        'status': 'pending',
      },
      {
        'id': 6,
        'title': 'Plan team offsite',
        'due_date': 'Sep 18, 2024',
        'due_time': null,
        'priority': 'low',
        'status': 'pending',
      },
    ];

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 6),
      itemCount: mockTasks.length,
      itemBuilder: (context, index) {
        return _buildTaskTile(context, mockTasks[index]);
      },
    );
  }

  Widget _buildTaskTile(BuildContext context, Map<String, dynamic> task) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isCompleted = task['status'] == 'completed';
    final priority = (task['priority'] ?? 'medium').toString().toLowerCase();

    Color flagColor = Colors.grey;
    if (priority == 'high') flagColor = AppTheme.redPriority;
    if (priority == 'medium') flagColor = AppTheme.yellowPriority;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : AppTheme.cardWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
      ),
      child: Row(
        children: [
          // Checkbox (Mockup 10)
          GestureDetector(
            onTap: () async {
              final newStatus = isCompleted ? 'pending' : 'completed';
              await ref.read(apiClientProvider).updateTask(task['id'], {'status': newStatus});
              ref.invalidate(mobileTasksProvider(_activeFilter));
            },
            child: Container(
              width: 22,
              height: 22,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(6),
                border: Border.all(
                  color: isCompleted ? AppTheme.brandBlue : const Color(0xFFCBD5E1),
                  width: 1.8,
                ),
                color: isCompleted ? AppTheme.brandBlue : Colors.transparent,
              ),
              child: isCompleted ? const Icon(Icons.check_rounded, size: 14, color: Colors.white) : null,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  task['title'] ?? 'Task',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                    decoration: isCompleted ? TextDecoration.lineThrough : null,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${task['due_date'] ?? ''}${task['due_time'] != null ? ', ${task['due_time']}' : ''}',
                  style: TextStyle(
                    fontSize: 12,
                    color: (task['due_date'] == 'Today' && priority == 'high') ? AppTheme.redPriority : AppTheme.textSecondaryLight,
                    fontWeight: (task['due_date'] == 'Today' && priority == 'high') ? FontWeight.w700 : FontWeight.w400,
                  ),
                ),
              ],
            ),
          ),
          // Priority Flag (Mockup 10)
          Icon(Icons.outlined_flag_rounded, color: flagColor, size: 20),
        ],
      ),
    );
  }
}

class CreateTaskModal extends ConsumerStatefulWidget {
  const CreateTaskModal({super.key});

  @override
  ConsumerState<CreateTaskModal> createState() => _CreateTaskModalState();
}

class _CreateTaskModalState extends ConsumerState<CreateTaskModal> {
  final _titleController = TextEditingController();
  final _descController = TextEditingController();
  String _selectedPriority = 'high';
  DateTime? _dueDate = DateTime(2024, 9, 12);
  TimeOfDay? _dueTime = const TimeOfDay(hour: 10, minute: 0);

  @override
  void dispose() {
    _titleController.dispose();
    _descController.dispose();
    super.dispose();
  }

  Future<void> _submitTask() async {
    final title = _titleController.text.trim();
    if (title.isEmpty) return;

    final formattedDate = _dueDate != null ? DateFormat('yyyy-MM-dd').format(_dueDate!) : null;
    final formattedTime = _dueTime != null ? '${_dueTime!.hour.toString().padLeft(2, '0')}:${_dueTime!.minute.toString().padLeft(2, '0')}' : null;

    try {
      await ref.read(apiClientProvider).createTask(
            title: title,
            description: _descController.text.trim(),
            dueDate: formattedDate,
            dueTime: formattedTime,
            priority: _selectedPriority,
          );
      if (mounted) Navigator.pop(context);
    } catch (_) {
      if (mounted) Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      height: MediaQuery.of(context).size.height * 0.88,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.slateCard : Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header Bar (Mockup 11)
            Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
                  onPressed: () => Navigator.pop(context),
                ),
                const SizedBox(width: 8),
                const Text(
                  'Create Task',
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
                ),
              ],
            ),
            const SizedBox(height: 20),

            // Task Name Input Field (Mockup 11)
            const Text('Task name', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            TextField(
              controller: _titleController,
              decoration: InputDecoration(
                hintText: 'Enter task name',
                hintStyle: const TextStyle(fontSize: 14, color: AppTheme.textSecondaryLight),
                filled: true,
                fillColor: isDark ? AppTheme.obsidianBlack : const Color(0xFFF8FAFC),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Description Field (Mockup 11)
            const Text('Description', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            TextField(
              controller: _descController,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: 'Add description (optional)',
                hintStyle: const TextStyle(fontSize: 14, color: AppTheme.textSecondaryLight),
                filled: true,
                fillColor: isDark ? AppTheme.obsidianBlack : const Color(0xFFF8FAFC),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Due Date Selector (Mockup 11)
            const Text('Due date', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            InkWell(
              onTap: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: DateTime.now(),
                  firstDate: DateTime.now(),
                  lastDate: DateTime.now().add(const Duration(days: 365)),
                );
                if (picked != null) setState(() => _dueDate = picked);
              },
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                decoration: BoxDecoration(
                  color: isDark ? AppTheme.obsidianBlack : const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.calendar_today_rounded, size: 18, color: AppTheme.textSecondaryLight),
                    const SizedBox(width: 12),
                    Text(
                      _dueDate == null ? 'Select date' : DateFormat('MMM d, yyyy').format(_dueDate!),
                      style: TextStyle(
                        fontSize: 14,
                        color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Due Time Selector (Mockup 11)
            const Text('Due time', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            InkWell(
              onTap: () async {
                final picked = await showTimePicker(
                  context: context,
                  initialTime: TimeOfDay.now(),
                );
                if (picked != null) setState(() => _dueTime = picked);
              },
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                decoration: BoxDecoration(
                  color: isDark ? AppTheme.obsidianBlack : const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: isDark ? AppTheme.slateBorder : AppTheme.borderSlate),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.access_time_rounded, size: 18, color: AppTheme.textSecondaryLight),
                    const SizedBox(width: 12),
                    Text(
                      _dueTime == null ? 'Select time' : _dueTime!.format(context),
                      style: TextStyle(
                        fontSize: 14,
                        color: isDark ? AppTheme.textPrimaryDark : AppTheme.textPrimaryLight,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Priority Radio Buttons: Low, Medium, High (Mockup 11)
            const Text('Priority', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
            const SizedBox(height: 10),
            Row(
              children: [
                _buildPriorityRadio(label: 'Low', value: 'low'),
                const SizedBox(width: 16),
                _buildPriorityRadio(label: 'Medium', value: 'medium'),
                const SizedBox(width: 16),
                _buildPriorityRadio(label: 'High', value: 'high', activeColor: AppTheme.redPriority),
              ],
            ),
            const SizedBox(height: 32),

            // Create Task Navy Button (Mockup 11)
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _submitTask,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primaryNavy,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  elevation: 0,
                ),
                child: const Text('Create Task', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPriorityRadio({required String label, required String value, Color activeColor = AppTheme.brandBlue}) {
    final isSelected = _selectedPriority == value;

    return GestureDetector(
      onTap: () => setState(() => _selectedPriority = value),
      child: Row(
        children: [
          Container(
            width: 18,
            height: 18,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: isSelected ? activeColor : const Color(0xFF94A3B8),
                width: isSelected ? 5.5 : 1.8,
              ),
            ),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 14,
              fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
              color: isSelected ? activeColor : AppTheme.textSecondaryLight,
            ),
          ),
        ],
      ),
    );
  }
}
