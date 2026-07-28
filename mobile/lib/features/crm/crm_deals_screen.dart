import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/bottom_nav.dart';
import '../../core/widgets/ai_floating_button.dart';
import '../../core/providers/providers.dart';

class CrmDealsScreen extends ConsumerStatefulWidget {
  const CrmDealsScreen({super.key});

  @override
  ConsumerState<CrmDealsScreen> createState() => _CrmDealsScreenState();
}

class _CrmDealsScreenState extends ConsumerState<CrmDealsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          'Lead Vault CRM',
          style: GoogleFonts.outfit(fontWeight: FontWeight.bold, color: Colors.white),
        ),
        backgroundColor: AppTheme.obsidianBlack,
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: AppTheme.primaryPurple,
          labelColor: Colors.white,
          unselectedLabelColor: AppTheme.textSecondaryDark,
          tabs: const [
            Tab(text: 'Deals Pipeline'),
            Tab(text: 'Contacts'),
          ],
        ),
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: AppTheme.premiumDarkGradient,
        ),
        child: TabBarView(
          controller: _tabController,
          children: [
            _buildDealsPipelineTab(),
            _buildContactsTab(),
          ],
        ),
      ),
      floatingActionButton: const AiFloatingActionButton(),
      bottomNavigationBar: const LinkPilotBottomNav(currentIndex: 3),
    );
  }

  Widget _buildDealsPipelineTab() {
    final dealsAsync = ref.watch(crmDealsProvider);
    final stages = ['Lead', 'Qualified', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'];

    return dealsAsync.when(
      data: (data) {
        final stagesData = data['stages'] as Map<String, dynamic>? ?? {};

        bool isEmpty = true;
        for (var stage in stages) {
          final list = stagesData[stage] as List<dynamic>? ?? [];
          if (list.isNotEmpty) {
            isEmpty = false;
            break;
          }
        }

        if (isEmpty) {
          return const Center(
            child: Text(
              'No deals found in pipeline.',
              style: TextStyle(color: AppTheme.textSecondaryDark),
            ),
          );
        }

        return ListView.builder(
          padding: const EdgeInsets.all(20),
          itemCount: stages.length,
          itemBuilder: (context, stageIndex) {
            final stage = stages[stageIndex];
            final List<dynamic> stageDeals = stagesData[stage] as List<dynamic>? ?? [];

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8.0),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        stage.toUpperCase(),
                        style: GoogleFonts.outfit(
                          color: AppTheme.secondaryPurple,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                          letterSpacing: 1.5,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppTheme.primaryPurple.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '${stageDeals.length} Deals',
                          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 8),

                if (stageDeals.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16.0),
                    child: Text('No deals in this stage', style: TextStyle(color: AppTheme.textSecondaryDark, fontSize: 13)),
                  )
                else
                  ...stageDeals.map((deal) => _buildDealCard(deal as Map<String, dynamic>)),
                const SizedBox(height: 16),
              ],
            );
          },
        );
      },
      loading: () => const Center(child: CircularProgressIndicator(color: AppTheme.primaryPurple)),
      error: (err, stack) => const Center(
        child: Text('Error loading deals.', style: TextStyle(color: AppTheme.textSecondaryDark)),
      ),
    );
  }

  Widget _buildDealCard(Map<String, dynamic> deal) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: AppTheme.glassBox(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  deal['title'] ?? 'Deal',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                ),
              ),
              Text(
                '₹${deal['expected_revenue'] ?? '0'}',
                style: const TextStyle(color: AppTheme.accentTeal, fontWeight: FontWeight.bold, fontSize: 15),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            deal['company_name'] ?? 'N/A',
            style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
          ),
          const Divider(color: AppTheme.slateBorder, height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  const Icon(Icons.person_outline, size: 14, color: AppTheme.textSecondaryDark),
                  const SizedBox(width: 4),
                  Text(
                    deal['contact_name'] ?? 'Assignee',
                    style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
                  ),
                ],
              ),
              PopupMenuButton<String>(
                icon: const Icon(Icons.more_horiz, color: Colors.white, size: 18),
                onSelected: (value) async {
                  await ref.read(apiClientProvider).updateCRMDeal(
                    deal['id'] as int,
                    {'stage': value},
                  );
                  ref.invalidate(crmDealsProvider);
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text('Moved deal stage to $value')),
                    );
                  }
                },
                itemBuilder: (context) => [
                  const PopupMenuItem(value: 'Lead', child: Text('Move to Lead')),
                  const PopupMenuItem(value: 'Qualified', child: Text('Move to Qualified')),
                  const PopupMenuItem(value: 'Proposal', child: Text('Move to Proposal')),
                  const PopupMenuItem(value: 'Negotiation', child: Text('Move to Negotiation')),
                  const PopupMenuItem(value: 'Closed Won', child: Text('Move to Closed Won')),
                  const PopupMenuItem(value: 'Closed Lost', child: Text('Move to Closed Lost')),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildContactsTab() {
    final contactsAsync = ref.watch(crmContactsProvider);

    return contactsAsync.when(
      data: (contacts) {
        if (contacts.isEmpty) {
          return const Center(
            child: Text('No contacts found.', style: TextStyle(color: AppTheme.textSecondaryDark)),
          );
        }

        return ListView.builder(
          padding: const EdgeInsets.all(20),
          itemCount: contacts.length,
          itemBuilder: (context, index) {
            final contact = contacts[index] as Map<String, dynamic>;
            final score = contact['lead_score'] ?? contact['score'] ?? 50;

            return Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(16),
              decoration: AppTheme.glassBox(),
              child: Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: AppTheme.primaryPurple.withOpacity(0.15),
                      shape: BoxShape.circle,
                      border: Border.all(color: AppTheme.primaryPurple, width: 1.5),
                    ),
                    child: Center(
                      child: Text(
                        score.toString(),
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                    ),
                  ),
                  const SizedBox(width: 16),

                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          contact['name'] ?? 'Unknown Contact',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${contact['job_title'] ?? 'Contact'} @ ${contact['company_name'] ?? contact['company'] ?? 'N/A'}',
                          style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
                        ),
                      ],
                    ),
                  ),

                  IconButton(
                    icon: const Icon(Icons.email_outlined, color: AppTheme.secondaryPurple, size: 20),
                    onPressed: () {},
                  ),
                  IconButton(
                    icon: const Icon(Icons.forum_outlined, color: AppTheme.accentTeal, size: 20),
                    onPressed: () {},
                  ),
                ],
              ),
            );
          },
        );
      },
      loading: () => const Center(child: CircularProgressIndicator(color: AppTheme.primaryPurple)),
      error: (err, stack) => const Center(
        child: Text('Error loading contacts.', style: TextStyle(color: AppTheme.textSecondaryDark)),
      ),
    );
  }
}
