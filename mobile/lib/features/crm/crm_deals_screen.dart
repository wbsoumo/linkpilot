import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/bottom_nav.dart';
import '../../core/widgets/ai_floating_button.dart';

class CrmDealsScreen extends ConsumerStatefulWidget {
  const CrmDealsScreen({super.key});

  @override
  ConsumerState<CrmDealsScreen> createState() => _CrmDealsScreenState();
}

class _CrmDealsScreenState extends ConsumerState<CrmDealsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  final List<Map<String, dynamic>> _deals = [
    {
      'id': 301,
      'title': 'Attio Enterprise Deal',
      'company': 'Attio Inc.',
      'revenue': '₹8,50,000',
      'stage': 'Proposal',
      'owner': 'Rohan Sharma',
    },
    {
      'id': 302,
      'title': 'Sharma Logistics CRM Integration',
      'company': 'Sharma Logistics',
      'revenue': '₹4,50,000',
      'stage': 'Qualified',
      'owner': 'Amit S.',
    },
    {
      'id': 303,
      'title': 'Merchant Wallet Webhook API Sync',
      'company': 'Novexa Pay',
      'revenue': '₹12,00,000',
      'stage': 'Negotiation',
      'owner': 'Soumojit Saha',
    },
  ];

  final List<Map<String, dynamic>> _contacts = [
    {
      'id': 401,
      'name': 'Sarah Jenkins',
      'designation': 'Partnership Manager',
      'company': 'Attio Inc.',
      'email': 'sarah@attio.com',
      'phone': '+1 (555) 019-2834',
      'score': 92,
    },
    {
      'id': 402,
      'name': 'Rohan Sharma',
      'designation': 'CTO',
      'company': 'Sharma Logistics',
      'email': 'rohan@sharmalog.com',
      'phone': '+91 98765 43210',
      'score': 85,
    },
  ];

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
    // Group deals by stage
    final stages = ['Qualified', 'Proposal', 'Negotiation'];

    return ListView.builder(
      padding: const EdgeInsets.all(20),
      itemCount: stages.length,
      itemBuilder: (context, stageIndex) {
        final stage = stages[stageIndex];
        final stageDeals = _deals.where((d) => d['stage'] == stage).toList();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Stage Title
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

            // Deals Cards list
            if (stageDeals.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 16.0),
                child: Text('No deals in this stage', style: TextStyle(color: AppTheme.textSecondaryDark, fontSize: 13)),
              )
            else
              ...stageDeals.map((deal) => _buildDealCard(deal)),
            const SizedBox(height: 16),
          ],
        );
      },
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
                  deal['title'],
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                ),
              ),
              Text(
                deal['revenue'],
                style: const TextStyle(color: AppTheme.accentTeal, fontWeight: FontWeight.bold, fontSize: 15),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            deal['company'],
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
                  Text(deal['owner'], style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12)),
                ],
              ),
              PopupMenuButton<String>(
                icon: const Icon(Icons.more_horiz, color: Colors.white, size: 18),
                onSelected: (value) {
                  setState(() {
                    deal['stage'] = value;
                  });
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Moved deal stage to $value')),
                  );
                },
                itemBuilder: (context) => [
                  const PopupMenuItem(value: 'Qualified', child: Text('Move to Qualified')),
                  const PopupMenuItem(value: 'Proposal', child: Text('Move to Proposal')),
                  const PopupMenuItem(value: 'Negotiation', child: Text('Move to Negotiation')),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildContactsTab() {
    return ListView.builder(
      padding: const EdgeInsets.all(20),
      itemCount: _contacts.length,
      itemBuilder: (context, index) {
        final contact = _contacts[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(16),
          decoration: AppTheme.glassBox(),
          child: Row(
            children: [
              // Contact Rating Score Badge
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
                    contact['score'].toString(),
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ),
              ),
              const SizedBox(width: 16),

              // Details
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      contact['name'],
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${contact['designation']} @ ${contact['company']}',
                      style: const TextStyle(color: AppTheme.textSecondaryDark, fontSize: 12),
                    ),
                  ],
                ),
              ),

              // Quick Action buttons
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
  }
}
