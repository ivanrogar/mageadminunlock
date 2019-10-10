<?php

declare(strict_types=1);

namespace JohnRogar\MageAdminUnlock\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Setup\Model\AdminAccount;
use Magento\Framework\App\State;
use Magento\User\Model\ResourceModel\User as AdminUser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UnlockCommand
 * @package JohnRogar\MageAdminUnlock\Console\Command
 * @SuppressWarnings(LongVariable)
 */
class UnlockCommand extends Command
{

    const USER_ID = 'user_id';

    private $state;
    private $objectManager;

    /**
     * UnlockCommand constructor.
     * @param State $state
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        State $state,
        ObjectManagerInterface $objectManager
    ) {
        $this->state = $state;
        $this->objectManager = $objectManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('johnrogar:mageadmin:reset')
            ->setAliases(['jo:ar'])
            ->setDescription('Reset admin user')
            ->addOption('new-password', null, InputOption::VALUE_REQUIRED, 'New password')
            ->addOption('admin-user', null, InputOption::VALUE_REQUIRED, 'Admin username', 'admin');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @SuppressWarnings(UnusedFormalParameter)
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
        }

        $output->writeln('MAGE UNLOCK v1.0');
        $output->writeln('=========================================' . PHP_EOL);

        if (!$input->getOption('new-password')) {
            $output->writeln('Parameter new-password missing');
            return -1;
        }

        // Magento DI is sometimes such a PAIN IN THE ASS
        // And yes, these probably could and will be added to constructor as factories or proxies
        $adminUser = $this->objectManager->get(AdminUser::class);
        $connection = $this->objectManager->get(ResourceConnection::class);
        $encryptor = $this->objectManager->get(EncryptorInterface::class);

        $data = $adminUser->loadByUsername($input->getOption('admin-user'));

        $output->writeln('Loading user: ' . $input->getOption('admin-user'));

        if (!$data) {
            $output->writeln('Account not found');
            return -1;
        }

        $output->writeln('Unlocking user ... ');

        $adminUser->unlock($data[self::USER_ID]);

        $output->writeln('Done');

        $data['admin-user'] = $data['username'];
        $data['admin-firstname'] = $data['firstname'];
        $data['admin-lastname'] = $data['lastname'];
        $data['admin-email'] = $data['email'];

        $data[AdminAccount::KEY_PASSWORD] = $input->getOption('new-password');

        $account = new AdminAccount(
            $connection->getConnection(),
            $encryptor,
            $data
        );

        $output->writeln('Setting new password ... ');

        $account->save();

        $output->writeln('Done');

        return 0;
    }
}
