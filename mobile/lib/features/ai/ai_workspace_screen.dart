import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/theme.dart';
import '../../core/widgets/ai_floating_button.dart';
import '../../core/providers/providers.dart';

class AiWorkspaceScreen extends ConsumerStatefulWidget {
  const AiWorkspaceScreen({super.key});

  @override
  ConsumerState<AiWorkspaceScreen> createState() => _AiWorkspaceScreenState();
}

class _AiWorkspaceScreenState extends ConsumerState<AiWorkspaceScreen> {
  final List<Map<String, dynamic>> _chatHistory = [
    {
      'sender': 'assistant',
      'text': 'Hello! I am your LinkPilot AI copilot. I can help you draft emails, analyze leads, or optimize campaign outreach templates. What can I do for you today?',
      'time': '10:00 AM'
    },
  ];

  final TextEditingController _chatController = TextEditingController();
  bool _isLoading = false;

  void _sendChatMessage() async {
    final userMessage = _chatController.text.trim();
    if (userMessage.isEmpty) return;

    setState(() {
      _chatHistory.add({
        'sender': 'user',
        'text': userMessage,
        'time': 'Just now',
      });
      _chatController.clear();
      _isLoading = true;
    });

    try {
      final client = ref.read(apiClientProvider);
      final response = await client.chatWithAssistant(userMessage);

      if (response.data['status'] == 'success') {
        final reply = response.data['data']['reply'] ?? 'Response received.';
        setState(() {
          _chatHistory.add({
            'sender': 'assistant',
            'text': reply,
            'time': 'Just now',
          });
        });
      } else {
        _addFallbackResponse(userMessage);
      }
    } catch (e) {
      _addFallbackResponse(userMessage);
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _addFallbackResponse(String query) {
    String reply = '';
    if (query.toLowerCase().contains('email')) {
      reply = 'Sure, I can help you draft emails. Try starting with: "Write an introductory email to a sales prospect in software logistics industry."';
    } else if (query.toLowerCase().contains('lead')) {
      reply = 'Sure, I can audit lead priority scores. Based on CRM metrics, Rohan Sharma has the highest lead priority score (92) due to recent WhatsApp interaction.';
    } else {
      reply = 'Understood. Let me fetch details from your campaigns database. I can trigger custom templates or optimization logs.';
    }

    setState(() {
      _chatHistory.add({
        'sender': 'assistant',
        'text': reply,
        'time': 'Just now',
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      appBar: AppBar(
        title: Text(
          'LinkPilot AI Copilot',
          style: GoogleFonts.outfit(fontWeight: FontWeight.bold, color: Colors.white),
        ),
        backgroundColor: AppTheme.obsidianBlack,
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: AppTheme.premiumDarkGradient,
        ),
        child: Column(
          children: [
            // Chat history list
            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.all(20),
                itemCount: _chatHistory.length,
                itemBuilder: (context, index) {
                  final chat = _chatHistory[index];
                  final isMe = chat['sender'] == 'user';
                  return Align(
                    alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 16),
                      padding: const EdgeInsets.all(16),
                      constraints: BoxConstraints(
                        maxWidth: MediaQuery.of(context).size.width * 0.75,
                      ),
                      decoration: isMe
                          ? AppTheme.glassBox(borderStyleColor: AppTheme.primaryPurple.withOpacity(0.5))
                          : BoxDecoration(
                              color: AppTheme.slateCard,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: AppTheme.slateBorder),
                            ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Icon(
                                isMe ? Icons.account_circle : Icons.psychology,
                                color: isMe ? Colors.white : AppTheme.primaryPurple,
                                size: 18,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                isMe ? 'You' : 'Copilot AI',
                                style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 12),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            chat['text'],
                            style: const TextStyle(color: Colors.white, fontSize: 14, height: 1.4),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),

            if (_isLoading)
              const Padding(
                padding: EdgeInsets.all(16.0),
                child: Center(
                  child: CircularProgressIndicator(color: AppTheme.primaryPurple),
                ),
              ),

            // Quick command helpers
            Container(
              height: 40,
              padding: const EdgeInsets.symmetric(vertical: 2),
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 20),
                children: [
                  _buildCommandChip('Draft a follow-up email'),
                  _buildCommandChip('Summarize today\'s leads'),
                  _buildCommandChip('Suggest templates rewrite'),
                ],
              ),
            ),

            // Input compositor
            Container(
              padding: const EdgeInsets.all(16),
              color: AppTheme.slateCard,
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _chatController,
                      style: const TextStyle(color: Colors.white),
                      decoration: const InputDecoration(
                        hintText: 'Ask Copilot to write, analyze or suggest...',
                        hintStyle: TextStyle(color: AppTheme.textSecondaryDark),
                        border: InputBorder.none,
                      ),
                      onSubmitted: (value) => _sendChatMessage(),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.arrow_upward, color: Colors.white),
                    style: IconButton.styleFrom(
                      backgroundColor: AppTheme.primaryPurple,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    onPressed: _sendChatMessage,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCommandChip(String text) {
    return Container(
      margin: const EdgeInsets.only(right: 8),
      child: ActionChip(
        backgroundColor: Colors.white.withOpacity(0.05),
        side: BorderSide(color: Colors.white.withOpacity(0.1)),
        label: Text(text, style: const TextStyle(color: Colors.white, fontSize: 11)),
        onPressed: () {
          _chatController.text = text;
        },
      ),
    );
  }
}
