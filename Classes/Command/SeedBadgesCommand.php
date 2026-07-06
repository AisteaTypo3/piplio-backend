<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(name: 'piplio:seed-badges', description: 'Seed initial milestone/streak badge data for the Piplio app')]
class SeedBadgesCommand extends Command
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-seed even if data already exists');
        $this->addOption('truncate', 't', InputOption::VALUE_NONE, 'Truncate the table before re-seeding. Only valid together with --force');
        $this->addOption('pid', null, InputOption::VALUE_REQUIRED, 'Page ID to assign records to', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_pipliobackend_badge');
        $pid = (int)$input->getOption('pid');
        $force = (bool)$input->getOption('force');
        $truncate = (bool)$input->getOption('truncate');

        if ($truncate && !$force) {
            $output->writeln('<error>Use --truncate only together with --force.</error>');
            return Command::INVALID;
        }

        $existing = $connection->count('*', 'tx_pipliobackend_badge', ['deleted' => 0]);
        if ($existing > 0 && !$force) {
            $output->writeln("<info>Already {$existing} badges in database. Use --force to re-seed.</info>");
            return Command::SUCCESS;
        }

        if ($force) {
            if (!$truncate) {
                $output->writeln('<error>Refusing to replace existing data without --truncate. Use --force --truncate for destructive reseeding.</error>');
                return Command::INVALID;
            }
            $connection->truncate('tx_pipliobackend_badge');
            $output->writeln('<comment>Table truncated.</comment>');
        }

        $now = time();
        $base = ['pid' => $pid, 'hidden' => 0, 'deleted' => 0, 'crdate' => $now, 'tstamp' => $now];
        $count = 0;

        foreach ($this->badges() as $badge) {
            $connection->insert('tx_pipliobackend_badge', array_merge($base, [
                'badge_id' => $badge['id'],
                'category' => $badge['category'],
                'title' => $badge['title'],
                'description' => $badge['description'],
                'icon' => $badge['icon'],
                'xp_required' => $badge['xpRequired'] ?? null,
                'streak_required' => $badge['streakRequired'] ?? null,
                'total_sessions_required' => $badge['totalSessionsRequired'] ?? null,
            ]));
            $count++;
        }

        $output->writeln("<info>✓ Seeded {$count} badges successfully.</info>");
        return Command::SUCCESS;
    }

    private function badges(): array
    {
        return [
            ['id' => 'first_steps', 'category' => 'milestone', 'title' => 'Erste Schritte', 'description' => 'Deine erste Runde gespielt!', 'icon' => 'rocket', 'totalSessionsRequired' => 1],
            ['id' => 'xp_50', 'category' => 'milestone', 'title' => 'Fleißige Biene', 'description' => '50 XP gesammelt', 'icon' => 'star', 'xpRequired' => 50],
            ['id' => 'xp_250', 'category' => 'milestone', 'title' => 'Lern-Fan', 'description' => '250 XP gesammelt', 'icon' => 'star2', 'xpRequired' => 250],
            ['id' => 'xp_750', 'category' => 'milestone', 'title' => 'Lern-Profi', 'description' => '750 XP gesammelt', 'icon' => 'trophy', 'xpRequired' => 750],
            ['id' => 'xp_1500', 'category' => 'milestone', 'title' => 'Lern-Star', 'description' => '1500 XP gesammelt', 'icon' => 'star', 'xpRequired' => 1500],
            ['id' => 'xp_3000', 'category' => 'milestone', 'title' => 'Lern-Held', 'description' => '3000 XP gesammelt', 'icon' => 'shield', 'xpRequired' => 3000],
            ['id' => 'xp_5000', 'category' => 'milestone', 'title' => 'Lern-Champion', 'description' => '5000 XP gesammelt', 'icon' => 'crown', 'xpRequired' => 5000],
            ['id' => 'xp_10000', 'category' => 'milestone', 'title' => 'Experte', 'description' => '10 000 XP gesammelt', 'icon' => 'lightning', 'xpRequired' => 10000],
            ['id' => 'xp_20000', 'category' => 'milestone', 'title' => 'Meister', 'description' => '20 000 XP gesammelt', 'icon' => 'diamond', 'xpRequired' => 20000],
            ['id' => 'xp_50000', 'category' => 'milestone', 'title' => 'Legende', 'description' => '50 000 XP gesammelt', 'icon' => 'crown', 'xpRequired' => 50000],
            ['id' => 'streak_3', 'category' => 'streak', 'title' => '3 Tage am Ball', 'description' => '3 Tage hintereinander gespielt', 'icon' => 'flame', 'streakRequired' => 3],
            ['id' => 'streak_7', 'category' => 'streak', 'title' => 'Super-Woche!', 'description' => '7 Tage hintereinander gespielt', 'icon' => 'flame2', 'streakRequired' => 7],
            ['id' => 'streak_14', 'category' => 'streak', 'title' => 'Zwei Wochen stark', 'description' => '14 Tage hintereinander gespielt', 'icon' => 'flame', 'streakRequired' => 14],
            ['id' => 'streak_30', 'category' => 'streak', 'title' => 'Monatsheld', 'description' => '30 Tage hintereinander gespielt', 'icon' => 'flame2', 'streakRequired' => 30],
            ['id' => 'streak_60', 'category' => 'streak', 'title' => 'Nicht aufzuhalten', 'description' => '60 Tage hintereinander gespielt', 'icon' => 'crown', 'streakRequired' => 60],
            ['id' => 'sessions_10', 'category' => 'milestone', 'title' => '10 Runden gespielt', 'description' => 'Schon 10 Runden dabei!', 'icon' => 'star2', 'totalSessionsRequired' => 10],
            ['id' => 'sessions_25', 'category' => 'milestone', 'title' => 'Fleißig dabei', 'description' => '25 Runden gespielt', 'icon' => 'book', 'totalSessionsRequired' => 25],
            ['id' => 'sessions_50', 'category' => 'milestone', 'title' => 'Lern-Marathon', 'description' => '50 Runden gespielt', 'icon' => 'rocket', 'totalSessionsRequired' => 50],
            ['id' => 'sessions_100', 'category' => 'milestone', 'title' => '100 Runden!', 'description' => 'Hundert Runden gespielt!', 'icon' => 'trophy', 'totalSessionsRequired' => 100],
        ];
    }
}
