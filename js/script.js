document.addEventListener('DOMContentLoaded', () => {
	const list = document.getElementById('subscriptions-list')
	const feedback = document.getElementById('subscriptions-feedback')

	function translate(text) {
		return typeof t === 'function' ? t('abonnieren', text) : text
	}

	function notify(kind, message) {
		feedback.className = kind
		feedback.textContent = message
		try {
			if (kind === 'success') OCP?.Toast?.success?.(message)
			if (kind === 'error') OCP?.Toast?.error?.(message)
		} catch (error) {}
	}

	async function request(action, values = {}) {
		const body = new FormData()
		body.append('action', action)
		Object.entries(values).forEach(([key, value]) => body.append(key, String(value)))
		const response = await fetch(OC.generateUrl('/apps/abonnieren/subscriptions'), {
			method: 'POST',
			headers: { requesttoken: OC.requestToken },
			body,
		})
		const payload = await response.json()
		if (!response.ok || !payload.success) throw new Error(payload.message || `HTTP ${response.status}`)
		return payload
	}

	function eventCheckbox(bit, eventMask, disabled) {
		const input = document.createElement('input')
		input.type = 'checkbox'
		input.checked = (eventMask & bit) !== 0
		input.disabled = disabled
		input.setAttribute('aria-label', String(bit))
		return input
	}

	function cellWith(child) {
		const cell = document.createElement('td')
		cell.appendChild(child)
		return cell
	}

	function renderRule(rule) {
		const row = document.createElement('tr')
		const objectCell = document.createElement('td')
		const objectLink = document.createElement('a')
		objectLink.href = rule.url
		objectLink.className = rule.type === 'folder' ? 'icon-folder' : 'icon-file'
		const name = document.createElement('strong')
		name.textContent = rule.name
		const path = document.createElement('small')
		path.textContent = rule.path
		objectLink.append(name, path)
		objectCell.appendChild(objectLink)
		row.appendChild(objectCell)

		const boxes = new Map()
		;[8, 1, 2, 4].forEach((bit) => {
			const box = eventCheckbox(bit, Number(rule.eventMask), bit === 1 && rule.type !== 'folder')
			boxes.set(bit, box)
			row.appendChild(cellWith(box))
		})
		const recursive = eventCheckbox(16, rule.recursive ? 16 : 0, rule.type !== 'folder')
		row.appendChild(cellWith(recursive))

		const actions = document.createElement('td')
		actions.className = 'abonnieren-row-actions'
		const save = document.createElement('button')
		save.type = 'button'
		save.textContent = translate('Save')
		const remove = document.createElement('button')
		remove.type = 'button'
		remove.textContent = translate('Remove subscription')
		actions.append(save, remove)
		row.appendChild(actions)

		save.addEventListener('click', async () => {
			let eventMask = 0
			boxes.forEach((box, bit) => { if (box.checked) eventMask |= bit })
			if (eventMask === 0) {
				notify('error', translate('Select at least one event or remove the subscription.'))
				return
			}
			save.disabled = true
			try {
				await request('save_rule', { nodeId: rule.nodeId, eventMask, recursive: recursive.checked })
				notify('success', translate('Subscription saved.'))
			} catch (error) {
				notify('error', translate('The subscription could not be saved.'))
			} finally {
				save.disabled = false
			}
		})

		remove.addEventListener('click', async () => {
			remove.disabled = true
			try {
				await request('delete_rule', { nodeId: rule.nodeId })
				row.remove()
				notify('success', translate('Subscription removed.'))
				if (!list.children.length) renderEmptyState()
			} catch (error) {
				notify('error', translate('The subscription could not be saved.'))
				remove.disabled = false
			}
		})
		return row
	}

	function renderEmptyState() {
		const row = document.createElement('tr')
		const cell = document.createElement('td')
		cell.colSpan = 7
		cell.className = 'abonnieren-empty-state'
		cell.textContent = translate('No file or folder has been subscribed yet.')
		row.appendChild(cell)
		list.replaceChildren(row)
	}

	async function loadRules() {
		feedback.className = ''
		feedback.textContent = translate('Loading subscriptions…')
		list.replaceChildren()
		try {
			const payload = await request('list_rules')
			feedback.textContent = ''
			if (!payload.rules.length) return renderEmptyState()
			payload.rules.forEach((rule) => list.appendChild(renderRule(rule)))
		} catch (error) {
			notify('error', translate('The subscriptions could not be loaded.'))
		}
	}

	document.getElementById('refresh-subscriptions')?.addEventListener('click', loadRules)
	loadRules()
})
