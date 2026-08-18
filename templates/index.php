<?php

script('abonnieren', 'script', ['version' => '1.0.3']);
style('abonnieren', 'style', ['version' => '1.0.3']);
?>

<div id="app-content">
	<main class="abonnieren-content">
		<header class="abonnieren-heading">
			<div>
				<h1><?php p($l->t('My subscriptions')); ?></h1>
				<p><?php p($l->t('Subscriptions can also be created directly in the Files sidebar.')); ?></p>
			</div>
			<button id="refresh-subscriptions" type="button"><?php p($l->t('Refresh')); ?></button>
		</header>
		<div id="subscriptions-feedback" role="status"></div>
		<div class="abonnieren-table-scroll">
			<table class="abonnieren-table">
				<thead>
					<tr>
						<th><?php p($l->t('File or folder')); ?></th>
						<th><?php p($l->t('Download')); ?></th>
						<th><?php p($l->t('Upload')); ?></th>
						<th><?php p($l->t('Modification')); ?></th>
						<th><?php p($l->t('Deletion')); ?></th>
						<th><?php p($l->t('Subfolders')); ?></th>
						<th><?php p($l->t('Actions')); ?></th>
					</tr>
				</thead>
				<tbody id="subscriptions-list"></tbody>
			</table>
		</div>
	</main>
</div>
