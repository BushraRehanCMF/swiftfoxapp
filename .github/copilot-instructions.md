# SwiftFox - GitHub Copilot Instructions

> **Version:** 1.0  
> **Last Updated:** January 2026  
> **Project:** SwiftFox V1 MVP

---

## 🎯 Project Overview

You are building **V1 of SwiftFox**, a SaaS product for managing WhatsApp messages in a shared inbox with basic automations and strict usage control during a 14-day free trial.

### Critical Business Rule

SwiftFox uses the official **WhatsApp Cloud API**. All WhatsApp traffic is billed to SwiftFox (platform owner), NOT the end customer. Therefore, **usage limits and enforcement are CRITICAL**.

---

## 📁 Project Structure

```
app-swiftfox/
├── backend/                    # Laravel 12 API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Middleware/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Jobs/
│   │   ├── Events/
│   │   ├── Listeners/
│   │   └── Policies/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/
│   │   └── factories/
│   ├── routes/
│   │   ├── api.php
│   │   └── web.php
│   ├── config/
│   └── tests/
├── frontend/                   # React + Vite SPA
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   ├── services/
│   │   ├── stores/
│   │   ├── utils/
│   │   └── types/
│   ├── public/
│   └── tests/
├── admin/                      # Super Admin SPA (separate)
│   └── src/
└── .github/
    └── copilot-instructions.md
```

---

## 🛠️ Tech Stack (LOCKED – DO NOT CHANGE)

### Backend

| Component | Technology |
|-----------|------------|
| Language | PHP 8.3+ |
| Framework | Laravel 12 |
| Architecture | Monolith |
| API Style | REST (JSON) |
| Authentication | Laravel Sanctum |
| Queues | Laravel Queue + Redis |

**Background jobs required for:**
- WhatsApp message sending
- Automation execution
- Webhook delivery

**Restrictions:**
- ❌ No microservices
- ❌ No GraphQL

### Database

| Component | Technology |
|-----------|------------|
| Database | MySQL 8+ |
| Structure | Single database |
| Multi-tenancy | Account-based (`account_id` column) |

**Restrictions:**
- ❌ No separate tenant databases

### Frontend

| Component | Technology |
|-----------|------------|
| Type | SPA (Single Page Application) |
| Framework | React (Vite) |
| Styling | Tailwind CSS |
| Theme | Light mode only |
| Primary Color | Green (emerald / green-600 range) |

**Restrictions:**
- ❌ No UI libraries (no MUI, no Chakra, no shadcn)

### Infrastructure

| Component | Technology |
|-----------|------------|
| Deployment | VPS |
| Web Server | Nginx |
| PHP | PHP-FPM |
| Cache | Redis |
| Process Manager | Supervisor |

**Restrictions:**
- ❌ No Docker in V1
- ❌ No Kubernetes

### Payments

| Component | Technology |
|-----------|------------|
| Provider | Stripe |
| Integration | Stripe webhooks + Checkout Session |

**Restrictions:**
- ❌ NO invoices in V1

---

## 🔐 Authentication & Roles (LOCKED)

### User Roles

| Role | Description |
|------|-------------|
| `member` | Basic team member |
| `owner` | Account owner with full access |
| `super_admin` | Platform administrator |

### Role Rules
- `member` and `owner` belong to exactly **one account**
- `super_admin` does **NOT** belong to any account

### Authentication
- Email + password only
- Laravel Sanctum for SPA auth
- Password reset via email

### Access Control

**Member:**
- ✅ Inbox, contacts, labels (read/write)
- ❌ Cannot manage users, WhatsApp, billing

**Owner:**
- ✅ Full account management
- ✅ Manage users
- ✅ Connect WhatsApp
- ✅ See trial & credit usage

**Super Admin:**
- ✅ Separate admin login (`/admin`)
- ✅ View all accounts & users
- ✅ Disable accounts
- ✅ Impersonate any account
- ❌ Cannot send WhatsApp messages
- ⚠️ Must NEVER be billed or limited by trial logic

---

## 💳 Trial & Billing Model (MANDATORY)

### Trial Rules
- Every new account starts with a **14-day free trial**
- Trial starts immediately on signup
- Trial allows REAL WhatsApp usage
- WhatsApp number connection is REQUIRED to test
- Trial usage must be **HARD-LIMITED**

### Trial Limits

| Limit | Value |
|-------|-------|
| Max conversations | 100 total (lifetime during trial) |
| Replies | Manual only |
| Campaigns/Broadcasts | ❌ Disabled |
| Webhooks | ❌ Disabled |

**Automations during trial:**
- ✅ Labels: allowed
- ✅ Assignments: allowed
- ⚠️ Auto replies: optional (count toward limit)

### After Trial (No Subscription)
- ❌ Disable sending messages
- 📖 Inbox becomes read-only
- 🔔 Show upgrade banner
- ✅ Incoming messages may still be stored

### Credit System
- **1 credit = 1 WhatsApp conversation (24h window)**
- Increment credit count on new conversation start
- Block outgoing messages when limit reached
- Credits do **NOT** reset during trial
- Paid plans: credits reset monthly
- Overages disabled in V1

---

## 📱 WhatsApp Business Integration

### Meta Embedded Signup Flow
1. Open Meta Embedded Signup modal
2. User logs in with Facebook
3. User selects/creates Meta Business Manager
4. User selects/creates WhatsApp Business Account
5. User verifies phone number via OTP
6. Grant SwiftFox app permissions (read/send messages)
7. Store returned WhatsApp Business Account ID & Phone Number ID

> **IMPORTANT:** Even though the user logs in with their Meta account, billing responsibility remains with SwiftFox because the Meta App is owned by SwiftFox.

---

## ✨ Feature Scope (V1)

### Product Architecture
- **Product App** → Multi-route SPA
- **Super Admin** → Separate SPA/layout

### Subscriber Features

#### 🔐 Authentication & Access
- Login / Logout
- Password reset
- Session persistence

#### 🧭 App Layout (Global)
- Top bar (account info, trial status)
- Left sidebar navigation
- Main content area
- Trial/upgrade banner (persistent when applicable)

#### 📨 Inbox (CORE FEATURE)
- Conversation list (left panel)
- Active conversation view (right panel)
- Message history
- Manual reply input
- Message status (sent/delivered)
- Assigned user display
- Applied labels display

#### 👤 Contacts
- List of contacts (auto-created)
- Contact detail view
- Labels on contacts
- Conversation history per contact

#### 🏷️ Labels
- Create label (name + color)
- Edit / delete label
- Used in: Inbox, Contacts, Automations

#### ⚙️ Automations
- List of automation rules
- Create / Edit / Delete rule
- Enable / disable rule
- Trigger + conditions + actions form

#### ⏱️ Business Hours
- Set working hours per day
- Timezone setting
- Used by automations

#### 🔗 Webhooks (Paid Only)
- Set webhook URL
- Enable / disable
- View last delivery status
- No logs UI in V1

#### 📊 Usage & Trial Status
- Conversations used / remaining
- Trial days remaining
- Upgrade CTA

#### 👥 Team Management (Owner Only)
- Invite user
- Remove user
- Assign role (member)
- View team list

#### 📱 WhatsApp Connection (Owner Only)
- Connect WhatsApp (Meta Embedded Signup)
- View connected number
- Disconnect number (with warning)

#### ⚠️ Upgrade & Limits
- Read-only mode after trial
- Upgrade CTA
- Feature gating

---

## 📐 Coding Standards & Conventions

### General Principles
- Write clean, readable, and maintainable code
- Follow SOLID principles
- Keep functions/methods small and focused
- Use meaningful variable and function names
- Add comments for complex logic only

### PHP/Laravel Standards
- Follow PSR-12 coding style
- Use Laravel's built-in features (Eloquent, Policies, Form Requests)
- Always validate input using Form Requests
- Use Resources for API responses
- Use Services for business logic (not in Controllers)
- Use Jobs for async operations
- Always scope queries by `account_id` for multi-tenancy
- Use database transactions for multi-step operations

```php
// ✅ Good: Scoped query
$contacts = Contact::where('account_id', $user->account_id)->get();

// ❌ Bad: Unscoped query
$contacts = Contact::all();
```

### React/TypeScript Standards
- Use functional components with hooks
- Use TypeScript for type safety
- Keep components small and reusable
- Use custom hooks for shared logic
- Co-locate styles with components
- Use `async/await` for API calls

```typescript
// ✅ Good: Typed props
interface ContactListProps {
  contacts: Contact[];
  onSelect: (id: string) => void;
}

// ❌ Bad: No types
function ContactList({ contacts, onSelect }) { ... }
```

### API Design
- Use RESTful conventions
- Version APIs: `/api/v1/...`
- Use proper HTTP methods (GET, POST, PUT, DELETE)
- Return consistent JSON responses
- Use proper HTTP status codes

```json
// Success response
{
  "data": { ... },
  "message": "Success"
}

// Error response
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": { ... }
  }
}
```

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| PHP Classes | PascalCase | `ContactController` |
| PHP Methods | camelCase | `getConversations()` |
| Database Tables | snake_case, plural | `contacts`, `automation_rules` |
| Database Columns | snake_case | `account_id`, `created_at` |
| React Components | PascalCase | `ConversationList.tsx` |
| React Hooks | camelCase with `use` | `useConversations()` |
| TypeScript Types | PascalCase | `Contact`, `Message` |
| API Endpoints | kebab-case | `/api/v1/automation-rules` |
| Environment Variables | SCREAMING_SNAKE_CASE | `WHATSAPP_API_KEY` |

---

## 🗄️ Database Schema Guidelines

### Core Tables

```
accounts
├── id
├── name
├── trial_ends_at
├── subscription_status (trial|active|cancelled|expired)
├── conversations_used
├── conversations_limit
├── timezone
├── created_at
└── updated_at

users
├── id
├── account_id (nullable for super_admin)
├── email
├── password
├── name
├── role (member|owner|super_admin)
├── email_verified_at
├── created_at
└── updated_at

whatsapp_connections
├── id
├── account_id
├── waba_id (WhatsApp Business Account ID)
├── phone_number_id
├── phone_number
├── status (active|disconnected)
├── created_at
└── updated_at

contacts
├── id
├── account_id
├── phone_number
├── name
├── created_at
└── updated_at

conversations
├── id
├── account_id
├── contact_id
├── assigned_user_id (nullable)
├── status (open|closed)
├── last_message_at
├── conversation_started_at (24h window tracking)
├── created_at
└── updated_at

messages
├── id
├── account_id
├── conversation_id
├── direction (inbound|outbound)
├── content
├── status (pending|sent|delivered|read|failed)
├── whatsapp_message_id
├── created_at
└── updated_at

labels
├── id
├── account_id
├── name
├── color
├── created_at
└── updated_at

automation_rules
├── id
├── account_id
├── name
├── trigger_type
├── conditions (JSON)
├── actions (JSON)
├── is_enabled
├── created_at
└── updated_at

business_hours
├── id
├── account_id
├── day_of_week (0-6)
├── start_time
├── end_time
├── is_enabled
├── created_at
└── updated_at
```

### Multi-tenancy Rules
- **ALWAYS** include `account_id` in queries
- Use global scopes or traits for automatic scoping
- Never expose data from other accounts
- Test multi-tenancy in every feature

---

## 🧪 Testing Requirements

### Backend Testing
- Write Feature tests for API endpoints
- Write Unit tests for Services
- Test multi-tenancy scoping
- Test trial/credit enforcement
- Use factories for test data

```php
// Example test for multi-tenancy
public function test_user_cannot_see_other_account_contacts()
{
    $user = User::factory()->create();
    $otherAccountContact = Contact::factory()->create();
    
    $this->actingAs($user)
        ->getJson('/api/v1/contacts')
        ->assertJsonMissing(['id' => $otherAccountContact->id]);
}
```

### Frontend Testing
- Test critical user flows
- Test form validations
- Test error states

---

## 🔄 Onboarding Flow

```
1. User signs up
   ↓
2. Trial starts (14 days)
   ↓
3. User is REQUIRED to connect WhatsApp
   ↓
4. User sees onboarding checklist
   ↓
5. User can start receiving & sending messages
   ↓
6. Trial banner shows: Days left + Conversations used
   ↓
7. Trial expires → Upgrade required
```

---

## 🚫 Explicitly Forbidden

**DO NOT:**
- ❌ Allow WhatsApp usage without limits
- ❌ Allow sending messages after trial expiry
- ❌ Allow campaigns during trial
- ❌ Charge the user during trial
- ❌ Let user connect WhatsApp outside Embedded Signup
- ❌ Use any tech not listed in the stack
- ❌ Add dark mode
- ❌ Use external UI component libraries
- ❌ Create unscoped database queries
- ❌ Put business logic in Controllers
- ❌ Skip input validation
- ❌ Hardcode configuration values

---

## ✅ Success Criteria

V1 is successful when:
- ✅ User can connect WhatsApp via Meta Embedded Signup
- ✅ Messages appear in inbox
- ✅ Trial limits are enforced correctly
- ✅ Platform never exceeds intended WhatsApp cost per trial user

---

## 💬 Communication Preferences

When responding to prompts:
- Be concise and direct
- Provide working code, not pseudo-code
- Follow the tech stack strictly
- Always consider multi-tenancy
- Always consider trial/credit enforcement
- Ask clarifying questions if requirements are ambiguous

---

## 🔧 Environment Variables

```env
# Application
APP_NAME=SwiftFox
APP_ENV=local
APP_DEBUG=true
APP_URL=https://app-swiftfox.test

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=swiftfox
DB_USERNAME=root
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# WhatsApp Cloud API
WHATSAPP_API_VERSION=v18.0
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_APP_ID=
WHATSAPP_APP_SECRET=
WHATSAPP_WEBHOOK_VERIFY_TOKEN=

# Stripe
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

# Trial Settings
TRIAL_DAYS=14
TRIAL_CONVERSATION_LIMIT=100
```

---

## 📚 Key References

- [Laravel Documentation](https://laravel.com/docs)
- [React Documentation](https://react.dev)
- [Tailwind CSS](https://tailwindcss.com/docs)
- [WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api)
- [Meta Embedded Signup](https://developers.facebook.com/docs/whatsapp/embedded-signup)
- [Stripe Documentation](https://stripe.com/docs/api)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)
