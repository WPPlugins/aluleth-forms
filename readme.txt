=== Aluleth Forms ===
Contributors: aluleth
Tags: form, contact form, form builder, payment form, paypal
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.19.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Light, beautiful forms that match your site, with entries on a board, contacts, consent and sending to your email tools.

== Description ==

Aluleth Forms is a form builder made to look good on your site from the first minute and to stay light. Build contact, quote, booking, survey or newsletter forms in a visual builder, follow every entry on a board, and send people to the email tools you already use.

= Start in seconds =

* **Nine designed templates:** contact, quote, booking, newsletter, call back, event registration, feedback, support and waitlist, each with its own style, messages and entry statuses.
* **Describe your form:** with the free Aluleth AI Writer plugin, write one sentence ("a booking form for a bike workshop") and get a draft to review in the builder.
* **Switch in a few clicks:** import forms and entries from Contact Form 7 (with Flamingo), WPForms, Elementor Forms and MetForm, even when that plugin is deactivated.

= A builder that shows the real thing =

* Drag fields, see the form in its real style, edit labels, widths and help texts in place. Undo, redo, phone preview.
* One page or several steps with a progress bar.
* Conditional logic: show a field only when other answers match.

= Matches your site =

* Button colour from your site's palette (theme.json or Elementor global colours), so the form follows your changes.
* Three styles, three corner shapes, your site's fonts; labels above, beside or inside the fields.
* A block, an Elementor widget and a shortcode.

= Light and accessible =

* One small stylesheet and script, only on pages with a form. No jQuery, and it works without JavaScript.
* Help and error texts are announced to screen readers; everything works with the keyboard.
* Built-in spam protection with no outside service and no cookie.

= Security against spam =

* Cloudflare Turnstile, hCaptcha, Google reCAPTCHA (v2 or v3) or CleanTalk: one default, and each form can choose another.
* Block addresses, domains and words, and use Akismet if it is active.
* Suspicious entries are kept aside in Spam, and you can mark them as not spam.

= Every entry in one place =

* Follow entries on a board with your own columns: drag a card to change its status, open it for the answers, history and team notes. List view and CSV export.
* An email to your team and an optional confirmation to the visitor.
* A dashboard: entries, contacts, conversion and subscribers over time.

= Take payments with PayPal, no fee from us =

* Price fields (choices with prices, extras, fixed price, free amount for donations) and a live total.
* PayPal, Pay Later or card, in PayPal's secure window; the money goes straight to your account.
* The amount is computed again on your site, and the entry is saved and sent only once the payment is confirmed.
* Sandbox and live mode, 24 currencies. Aluleth takes 0%.

= Settings for the whole site =

* Defaults for new forms, your own validation messages, a weekly email summary, entry retention.
* Access: choose what editors, authors or shop managers can do.
* AI agents (off by default): let an AI assistant connected through MCP read your forms and create drafts, with the WordPress Abilities API.

= Contacts and consent =

* One contact per person, with their history and newsletter consent (when and how they agreed).
* Import your list from a CSV or ZIP export (Mailchimp, Brevo, MailerLite, Kit…), pasted addresses or your users.

= Send entries to your tools =

* Mailchimp, Brevo, MailerLite, Kit (ConvertKit), Mautic, MailPoet, FluentCRM, and webhooks for Zapier, Make, n8n or your own code.
* Team alerts: get each new entry in Slack, Discord or Telegram, with the answers, the amount paid and a link to the entry.
* Lists, tags and field mapping per form. Sending runs in the background and failed sends are tried again.

Aluleth Forms is free and has no limit on forms, entries or contacts.

== Privacy ==

Aluleth Forms stores the answers people send in your site's database. The visitor's IP address is never stored: only a one-way hash of it, used to limit abuse. Contacts and entries are included in the WordPress personal data export and erasure tools.

Aluleth Forms does not send any data to outside services by itself. Data leaves your site only when you add an integration to a form:

* **Webhook:** the answers of each entry (and the contact's email, name, phone and newsletter consent) are posted to the address you enter, when the entry is received. What happens next depends on the service behind that address (for example Zapier, Make or n8n); read its terms and privacy policy.
* **MailPoet** and **FluentCRM** are plugins on your own site: the contact is saved in their tables, nothing is sent elsewhere by Aluleth Forms.
* **Mailchimp, Brevo, MailerLite, Kit, Mautic, Slack, Discord and Telegram:** see "External services" below.
* **Anti-spam services** (Cloudflare Turnstile, hCaptcha, Google reCAPTCHA, CleanTalk, Akismet) are used only when you turn them on in Aluleth › Settings › Security: see "External services" below. Aluleth › Settings › Data and privacy lists the ones you use, with a sentence for your privacy policy.

Entries kept aside as spam are stored like the others, without emails or sending to your tools.

Newsletter tools (Mailchimp, Brevo, MailerLite, Kit, Mautic, MailPoet, FluentCRM) only receive people who ticked a newsletter consent. Team alerts (Slack, Discord, Telegram) and webhooks receive every entry by default: you can limit them to entries with newsletter consent in the form's integration.

On payment forms, the answers wait in your site's database (a temporary record, up to 2 days) while the visitor is in PayPal's window, and become an entry once the payment is captured. Unpaid answers are deleted after a day. The entry keeps the amount, the items, the PayPal order and transaction ids and the payer's name and email address returned by PayPal. Paid entries are kept even with "Do not save entries".

When the weekly summary is on (Aluleth › Settings › General), it is emailed to the address you choose. It holds numbers and form names, no entry content.

"Keep entries for" deletes older entries for good once a day, and spam is deleted after 30 days unless you untick it. With "Do not save entries", the answers are only emailed and sent to your connections, then deleted from your site; contacts and consent are still saved.

The AI agents abilities (off by default) let an AI assistant that you connect to your site read what the signed-in WordPress user is allowed to see. What the assistant does with it is governed by its own provider's terms; Aluleth Forms sends nothing anywhere by itself.

When you use "Write my form" on the Create a form screen, your description of the form is sent through the Aluleth AI Writer plugin to the AI provider you connected in Settings › Connectors. Nothing else is sent, and only when you click the button.

The log of sends is kept 30 days (90 days for failed sends) and is removed with the contact by the WordPress erasure tool.

To show the conversion of your forms, Aluleth Forms counts how many times each form is shown: one number per form and per day, stored in your site. No cookie is set and nothing about the visitor is stored. Visits of logged-in editors are not counted. Developers can turn the counter off with the `aluleth_forms/count_views` filter.

== External services ==

Aluleth Forms connects to the services below only after you connect your own account in Aluleth › Connections (by pasting your API key) and add the service to a form in its Settings › Integrations. Nothing is sent before that.

When you connect an account or open a form's integrations, Aluleth Forms reads your lists, tags and fields from the service. When someone sends a form that uses the service and gave newsletter consent, it sends their email address, first name, last name, phone (when the form has these fields), the lists and tags you chose, and the answers of the fields you mapped.

* **Mailchimp** (Intuit Mailchimp), API at `*.api.mailchimp.com`. [Terms of use](https://mailchimp.com/legal/terms/), [Privacy policy](https://www.intuit.com/privacy/statement/).
* **Brevo** (Sendinblue), API at `api.brevo.com`. [Terms of use](https://www.brevo.com/legal/termsofuse/), [Privacy policy](https://www.brevo.com/legal/privacypolicy/).
* **MailerLite**, API at `connect.mailerlite.com`. [Terms of service](https://www.mailerlite.com/legal/terms-of-service), [Privacy policy](https://www.mailerlite.com/legal/privacy-policy).
* **Kit** (formerly ConvertKit), API at `api.kit.com`. [Terms of service](https://kit.com/terms), [Privacy policy](https://kit.com/privacy).
* **Mautic** is open-source software that you or your provider install on your own server: Aluleth Forms connects to the address you enter (its API at `/api`) with the user name and password of a Mautic API user. It reads your segments and contact fields, and creates or updates the contact and adds them to the segment you chose. The terms are those of whoever hosts your Mautic. [Mautic privacy policy](https://mautic.org/privacy-policy/).
* **Webhooks** go to the address you enter yourself (for example Zapier, Make or n8n): the answers of each entry are posted there.

Your API keys and passwords are stored in your site's database and are never shown again in full.

= Team alerts: Slack, Discord and Telegram =

Used only after you add the service to a form in its Settings › Integrations (and, for Telegram, connect your bot in Aluleth › Connections). When the form receives an entry, your site sends a message with the form's name, the answers that are not empty, the amount paid (on payment forms) and a link to the entry in your WordPress admin, which only signed-in users can open. "Send a test" sends a sample message with made-up answers.

* **Slack** (Slack Technologies, LLC, a Salesforce company), to the incoming webhook address you paste (`hooks.slack.com`). [User terms of service](https://slack.com/terms-of-service/user), [Privacy policy](https://slack.com/trust/privacy/privacy-policy).
* **Discord** (Discord Inc.), to the channel webhook address you paste: only Discord webhook addresses are accepted (`discord.com/api/webhooks/…`, and the older `discordapp.com`, `canary.discord.com` and `ptb.discord.com` forms that Discord also gives). The message is posted there when an entry arrives, as described above. [Terms of service](https://discord.com/terms), [Privacy policy](https://discord.com/privacy).
* **Telegram** (Telegram Messenger Inc.), through the Bot API at `api.telegram.org` with the token of the bot you create with @BotFather. When you connect the bot, your site reads its name; when you open a form's integrations, it reads the chats that wrote to the bot (their id and name) so that you can choose where messages go. [Terms of service](https://telegram.org/tos), [Bot API terms](https://telegram.org/tos/bot-developers), [Privacy policy](https://telegram.org/privacy).

= PayPal =

Used only after you paste the keys of your own PayPal app in Aluleth › Settings › Payments, and only on forms that have a price field.

* **PayPal** (PayPal Holdings, Inc., and its local companies, for example PayPal (Europe) S.à r.l. et Cie, S.C.A. in the European Union). On the pages that show such a form, the visitor's browser loads PayPal's script from `www.paypal.com/sdk/js`, which receives the visitor's IP address and browser details. When the visitor clicks a PayPal button, your site sends the items, their prices, the total, the form's name and a reference to PayPal's API (`api-m.paypal.com`, or `api-m.sandbox.paypal.com` in test mode) to create the order; after the visitor approves it in PayPal's window, your site asks PayPal to capture it and receives the payment status, the transaction id and the payer's name and email address. The payment details the visitor types (card, PayPal login) are sent by the visitor to PayPal directly and never reach your site. "Save and check the keys" sends your Client ID and secret to PayPal to get an access token. [User agreement](https://www.paypal.com/webapps/mpp/ua/useragreement-full), [Privacy statement](https://www.paypal.com/webapps/mpp/ua/privacy-full).

= Anti-spam services =

These are used only after you paste your own keys in Aluleth › Settings › Security and choose the service, by default or on a form. Nothing is sent before that.

* **Cloudflare Turnstile** (Cloudflare, Inc.). On the pages that show a form using it, the visitor's browser loads the Turnstile script from `challenges.cloudflare.com`, which receives the visitor's IP address and browser details to run the check. When the form is sent, your site sends the check result and the visitor's IP address to `challenges.cloudflare.com/turnstile/v0/siteverify`. [Terms of use](https://www.cloudflare.com/website-terms/), [Privacy policy](https://www.cloudflare.com/privacypolicy/), [Turnstile privacy addendum](https://www.cloudflare.com/turnstile-privacy-policy/).
* **hCaptcha** (Intuition Machines, Inc.). The visitor's browser loads the script from `js.hcaptcha.com`, which receives the visitor's IP address and browser details. When the form is sent, your site sends the check result and the visitor's IP address to `api.hcaptcha.com/siteverify`. [Terms of service](https://www.hcaptcha.com/terms), [Privacy policy](https://www.hcaptcha.com/privacy).
* **Google reCAPTCHA** (Google LLC). The visitor's browser loads the script from `www.google.com/recaptcha`, which receives the visitor's IP address, browser details and how they use the page. When the form is sent, your site sends the check result and the visitor's IP address to `www.google.com/recaptcha/api/siteverify`. [Terms of service](https://policies.google.com/terms), [Privacy policy](https://policies.google.com/privacy).
* **CleanTalk** (CleanTalk Inc.). No script is added to your pages. When a form using it is sent, your site sends the email address, the name, the message, the visitor's IP address and browser, and how long the form took to fill to `moderate.cleantalk.org`. [Terms of service](https://cleantalk.org/publicoffer), [Privacy policy](https://cleantalk.org/privacy).
* **Akismet** (Automattic Inc.), only when the Akismet plugin is active and connected on your site and you tick it. When a form is sent, your site sends the name, email address, message, the visitor's IP address, browser and the page they came from to Akismet through the Akismet plugin. [Terms of service](https://akismet.com/tos/), [Privacy policy](https://automattic.com/privacy/).

When you click "Save and check the keys", your site sends your secret key with the answer of the test check shown on that screen (or a made-up value when there is none) to the service's address above, to tell you whether the keys work. For CleanTalk, it sends a short test message with your access key.

== Frequently Asked Questions ==

= How do I show a form? =

Add the "Aluleth Form" block to a page and choose your form, or paste the shortcode shown on the form's screen, for example [aluleth_form id="12"].

= Does it work with Elementor? =

Yes. Drag the "Aluleth Form" widget from the Aluleth category, choose the form and its style, then adjust colours, fields and the button in the Style tab. Colours and fonts can come from your site's global settings.

= Can I bring my existing list? =

Yes. In Aluleth › Contacts › Import contacts, upload the export of your old tool (CSV, TXT or ZIP, any encoding, commas or semicolons), check where each column goes and import. Mailchimp's ZIP export can be imported as it is. Nobody is marked as subscribed unless you confirm they agreed to receive your emails, and people who unsubscribed or bounced are never emailed.

= Can I import my Contact Form 7, WPForms, Elementor or MetForm forms? =

Yes. Forms › Add new › "Import your forms and entries", tick the forms and import. Entries come too (Contact Form 7 entries need the Flamingo plugin, WPForms entries need WPForms Pro). Then replace the old shortcode or widget by the Aluleth form.

= Can it send contacts to Mailchimp, Brevo, MailerLite, Kit, Mautic, MailPoet, FluentCRM or Zapier? =

Yes. Connect your account in Aluleth › Connections (paste your API key, or for Mautic its address and an API user; MailPoet and FluentCRM need no key), then open the form › Settings › Integrations and choose the lists and tags. Webhooks work with Zapier, Make, n8n or your own code. Aluleth › Connections also lists the recent sends.

= Can I get new entries in Slack, Discord or Telegram? =

Yes. For Slack or Discord, create an incoming webhook for the channel (Slack: an app with Incoming Webhooks; Discord: channel settings › Integrations › Webhooks), then paste its address in the form › Settings › Integrations. For Telegram, create a bot with @BotFather, paste its token in Aluleth › Connections, send it a message (or add it to your group), then choose that chat in the form's integrations. Use "Send a test" to check.

= Does it slow down my site? =

No. Aluleth Forms loads its small stylesheet and script only on pages that show a form, uses no jQuery and no outside service unless you turn on a captcha, and sends entries to your tools in the background.

= Is it GDPR friendly? =

Aluleth Forms stores no IP address (only a one-way hash used against abuse), sets no cookie, records newsletter consent with its date and wording, never subscribes someone who left, and works with the WordPress personal data export and erasure tools. Only you can decide what your forms collect and why, so add a consent box and link your privacy policy where needed.

= What happens to my data if I delete the plugin? =

It is kept, so nothing is lost if you install the plugin again. To erase your forms, entries and contacts for good when you delete the plugin, tick them in Aluleth › Settings › Data and privacy first.

= How do I stop spam? =

The built-in protection works without any setting. To add a captcha, open Aluleth › Settings › Security, choose Cloudflare Turnstile, hCaptcha, reCAPTCHA or CleanTalk, paste your keys and click "Save and check the keys". It then protects every form, unless a form chooses another check in its Settings › Spam protection. Suspicious entries go to Spam, at the top of the entries screen.

= Can I take payments? =

Yes, with PayPal and no fee from Aluleth. Create an app in your PayPal developer dashboard, paste its Client ID and secret in Aluleth › Settings › Payments (test keys first, then live ones), then add a price field to a form: "Choice with prices", "Extras with prices", "Fixed price" or "Free amount", and a "Total". The send button becomes the PayPal buttons. Refunds are made in your PayPal account.

= Can my team use it without being administrators? =

Yes. In Aluleth › Settings › Access, tick what each role can do: create and edit forms (editors by default), see entries, change their status and add notes, see contacts, or change the settings. Administrators can always do everything.

= Can an AI assistant work with my forms? =

Yes, if you want. Turn on AI agents in Aluleth › Settings › Access and choose what it may do. An assistant connected to WordPress through MCP (for example with the MCP Adapter plugin) can then read your forms and numbers, create draft forms, and, only if you tick it, read entries or change their status. It always acts with the rights of the WordPress user it signs in with.

= Where are the entries? =

In Aluleth › Entries, on a board (one column per status) or in a list. Each entry links to the contact who sent it. To rename, add or reorder the columns of a form, open the form › Settings › Entry statuses.

== Screenshots ==

1. The builder: field palette, the form in its real style, and the settings of the selected field.
2. Create a form: describe it to the AI, or start from one of nine designed templates.
3. A form on the site, with the site's own colour, corners and font.
4. Entries on a board with your own columns. Drag a card to change its status.
5. The Aluleth dashboard: entries, new contacts, conversion and what is waiting for you.
6. Send entries to Mailchimp, Brevo, MailerLite, Kit, Mautic, MailPoet, FluentCRM, Slack, Discord, Telegram or a webhook, per form.
7. Import your forms and entries from Contact Form 7, WPForms, Elementor Forms and MetForm.

== Changelog ==

= 0.19.3 =
* The dashboard period, the entries view and a new blank form are only saved or created from links that carry a nonce.
* Form views are counted only for forms shown on a page (with their signed token), at most 30 an hour from one address.
* Only site administrators can change what each role can do (Settings › Access).

= 0.19.2 =
* Database tables, saved settings and temporary data now all use the "aluleth" prefix. Existing tables and data are renamed automatically on update; nothing is lost. Update your other Aluleth plugins at the same time.
* A form's design (accent colour, corners, font) is applied on the form itself instead of a style block, and the no-JavaScript fallback for conditional fields is in the plugin's stylesheet.
* Form fields and settings are cleaned as soon as they are received in the builder.
* The External services section explains more precisely which Discord addresses are accepted.

= 0.19.1 =
* Database queries use the WordPress identifier placeholder for table names, and the list filters no longer build SQL on the fly. Same results, cleaner code.
* Code comments explain why the Cloudflare Turnstile addresses are remote (the service only works from Cloudflare).

= 0.19.0 =
* Entries can store extra data from add-ons (for example a quiz score). The database table gets one new column; nothing changes for existing forms.
* New hooks for add-ons: entry data, success message, email tokens and rows, board card labels, entry panel blocks, CSV columns, extra views of the entries screen, field types that can drive conditions, files attached to the form emails, template groups with a badge and colour on the "Create a form" screen, and an "alf:done" event in the browser after a form is sent.

= 0.18.1 =
* Email fields refuse an address whose domain contains a symbol pasted by mistake (for example "◘"), instead of accepting an address that can never receive the email.
* Accented domains (ana@münchen.de) are kept correctly when the form is sent without JavaScript.

= 0.18.0 =
* Payments: quantities, tax and discount lines are shown in the entry, the emails and the CSV export, with the payment method and the order number when there is one.
* For developers: hooks so add-ons can add their own field types (aluleth_forms/field_types, clean_field, validate_field, render_field, value_text), price them (payment_cart, payment_currency), take payments their own way (paypal_allowed, submit_payment_form, pay_area, pay_methods, payment_saved, payment_view, payment_receipt, payment_message), add payment methods to Aluleth › Settings › Payments, and extend the builder with wp.hooks.
* Aluleth Core 0.13.0: PayPal orders in another currency, with tax and discount.

= 0.17.0 =
* New connections, free: Mautic (segments, tags, contact fields), and team alerts in Slack, Discord and Telegram with the answers, the amount paid and a link to the entry. "Send a test" for each of them; alerts can go out for every entry or only with newsletter consent.
* Aluleth › Connections is grouped: email marketing and CRM, team alerts, automation.
* Aluleth › Settings › General warns when no sender email is set, and says which address is used instead.
* Aluleth Core 0.12.0.

= 0.16.0 =
* Payments with PayPal, no fee from Aluleth: price fields (choice with prices, extras, fixed price, free amount) and a live total; PayPal, Pay Later and card buttons; the amount is computed on the server; the entry is saved, emailed and sent to your tools only once the payment is captured; a daily check finishes payments whose page was closed too early.
* Aluleth › Settings › Payments: test and live keys with a real key check, currency, button colour and shape, card and Pay Later on or off, receipt to the payer.
* Board: "Paid" badge and a Payment block (items, amount, PayPal transaction, payer). Payment columns in the CSV export, payments received in the weekly summary, payment details for the AI agents.
* Aluleth Core 0.11.0.

= 0.15.0 =
* Aluleth › Settings › General: defaults for new forms (style, labels above, beside or inside the fields, help text position, error list at the top) with a live preview, your own validation messages, weekly email summary, entry retention, spam cleanup after 30 days, notifications sender, "Do not save entries".
* New layout options in each form's Design tab: labels above, beside or inside, help text under the label, errors also listed at the top (with links to the fields).
* Aluleth › Settings › Access: what each role can do. Editors keep creating forms; entries, contacts and settings stay with administrators unless you choose otherwise.
* AI agents (off by default): abilities aluleth-forms/get-forms, create-form, get-entries and update-entry for the WordPress Abilities API and MCP.
* Aluleth Core 0.10.0.

= 0.14.0 =
* New Aluleth › Settings with a side menu: Security, Connections, Data and privacy.
* Security: Cloudflare Turnstile, hCaptcha, Google reCAPTCHA v2 and v3, and CleanTalk, with a key check. A default check for every form, and a choice per form.
* Built-in protection settings: minimum time to fill a form and entries per visitor per hour.
* Blocklist of email addresses, domains and words, and Akismet.
* Spam: suspicious entries are kept aside, without emails or sending. "Spam" link on the entries screen, "Mark as spam" and "Not spam" on each entry.
* Aluleth Core 0.9.0.

= 0.13.0 =
* New Aluleth › Settings screen: choose what is erased for good when you delete the plugin (nothing by default). Shared contacts are erased only with the last Aluleth plugin.
* Deactivating the plugin stops its scheduled tasks. "Forms" and "Settings" links on the Plugins screen.
* Aluleth Core 0.7.0.


= 0.12.1 =
* Security review fixes. Only administrators can change where entries are sent (integrations, notification address) and the redirect page; editors no longer see webhook addresses and secrets.
* A public form can no longer change an existing contact's details, subscribe again someone who unsubscribed, or record a consent in their name; such people are sent to your tools without consent.
* Confirmation emails are limited to 3 per address and per hour. The "Sent from" page is kept only when it is a page of your site. Failed no-JavaScript sends no longer write anything in the database.
* The forms list of the REST API is for editors only. Rate limit per form; developers can set the visitor IP behind a proxy with the `aluleth/client_ip` filter. New integrations use double opt-in when the tool offers it.

= 0.12.0 =
* Import forms and entries from Contact Form 7 (with Flamingo entries), WPForms (entries of WPForms Pro), Elementor Forms (Elementor Pro submissions) and MetForm: fields, choices, required fields, widths, steps, consent boxes, button text, success message and notification address. Entries keep their date and page, go to the column you choose, and can be linked to contacts (never subscribed). Fields with no matching type yet (file uploads…) are listed. Your old forms are not changed.

= 0.11.0 =
* New "Create a form" screen: describe the form you need and Aluleth AI Writer builds it (opened as a draft in the builder so you review every field), or start from one of nine designed templates with their own style, button text, messages and entry statuses, or start blank.

= 0.10.0 =
* Design tab: button and accent colour from your site's colours (theme.json palette or Elementor global colours, kept in sync through the site's CSS variables) or your own colour, with the text colour on the button chosen for contrast; corners (square, rounded, pill); font from your site's fonts. The Elementor widget's Style tab still has the last word.
* Brevo: system attributes are no longer offered in the field mapping.

= 0.9.0 =
* New integrations: Mailchimp (audiences, tags, merge fields, double opt-in), Brevo (lists, attributes), MailerLite (groups, fields) and Kit (forms, tags, custom fields, double opt-in).
* Aluleth › Connections: connect each account with its API key, test the connection, disconnect. Keys are never shown again in full.
* People who unsubscribed in these tools are never subscribed again.
* Builder › Integrations: a Refresh button reloads the lists, tags and fields of a connected tool.

= 0.8.1 =
* MailPoet: when MailPoet cannot send its confirmation email, the send says so clearly (the person is on the list but cannot confirm yet) and points to MailPoet's sending settings.

= 0.8.0 =
* Integrations: send entries to MailPoet (lists, tags, custom fields), FluentCRM (lists, tags, double opt-in, custom fields) and webhooks (JSON or form fields, optional HMAC signature, "Send a test" button). Form › Settings › Integrations.
* Sends run in the background after the visitor sees your message; temporary errors are tried again (after 1 minute, 5 minutes, 30 minutes and 2 hours).
* Each entry shows where it was sent, with a "Send again" button for failed sends. New screen Aluleth › Connections with the tools and recent sends; the dashboard warns when sends failed.
* Newsletter tools only receive people who gave newsletter consent; people who unsubscribed in MailPoet or FluentCRM are never subscribed again.

= 0.7.1 =
* Dashboard: the conversion only compares the entries received since the views counter started, so it is right from the first day.
* Entries show the person's name when the name field has another key (for example "your_name").

= 0.7.0 =
* New Aluleth dashboard: key numbers with the change from the period before (entries, new contacts, form conversion, newsletter subscribers), a chart of entries and new contacts with details on hover, the entries waiting for you, your forms with views, entries, conversion and trend, where new contacts come from, recent activity and your Aluleth tools. 7 days, 30 days or 12 months.
* Form views counter for the conversion rate: one number per form and per day, no cookie, no personal data, editors not counted.
* Aluleth Core 0.3.0.

= 0.6.1 =
* Entries board: the confirmation message after moving a card now has its dark background.

= 0.6.0 =
* Entries board: one column per status, drag and drop, details panel with the answers, the contact's tags and history, and internal notes. Works with the keyboard and without JavaScript (each card opens the entry's page).
* Your own entry statuses per form (Settings › Entry statuses): rename, add, reorder or remove up to 8 columns.
* Notes on entries, visible to your team only.
* Dates are shown in your site's date format.

= 0.5.0 =
* Import contacts (Aluleth › Contacts › Import contacts): CSV, TXT or ZIP files from any tool, pasted addresses or WordPress users. Columns are recognised automatically (English, French, Spanish titles), file encoding and separators are detected, big lists are imported in batches with a progress bar, and the rows that could not be imported can be downloaded.
* Consent-safe: contacts are imported as subscribed only when you confirm their consent (recorded), unsubscribed, bounced and spam statuses from the old tool are kept and added to the suppression list, and an import never re-subscribes someone who left.
* Aluleth Core 0.2.0.

= 0.4.0 =
* Elementor widget "Aluleth Form": form picker, style, colours (with the site's global colours), label and field typography, spacing, corners, borders, button and form box. The form is never sent from inside the editor.
* The shortcode accepts a style, for example [aluleth_form id="12" style="dark"], handy in other page builders.

= 0.3.1 =
* Builder: the "Shown if…" badge no longer covers the field label.

= 0.3.0 =
* Conditional logic: show a field only when other answers match (all or any rule; is, is not, contains, empty, filled in, more or less than). Hidden fields are neither required nor saved, and steps with no visible field are skipped.
* One-third width, for forms on three columns.

= 0.2.0 =
* Visual builder: field palette with search, live preview in the chosen style, drag and drop, field settings, undo and redo, keyboard shortcuts, Design and Settings tabs, desktop and phone preview.

= 0.1.0 =
* First version: forms, steps, entries, contacts, notifications, block and shortcode.
