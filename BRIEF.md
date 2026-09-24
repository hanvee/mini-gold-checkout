# JEINDO / JUALEMAS.ID — CANDIDATE BRIEF | FULL STACK WEB DEVELOPER
**AI Skill Test | Version September 24, 2026**

## AI Skill Test
**Full Stack Web Developer | Jeindo & jualemas.id**

**CASE STUDY:** Mini Gold Checkout
**DURATION:** 3 hours of work + 30 minutes of discussion
**TECHNOLOGY:** Laravel; Blade or Livewire; MySQL, PostgreSQL, or SQLite
**AI USAGE:** Mandatory for part of the work; tools are free choice, free versions are allowed.

---

## 01 / Purpose and Context

Jeindo manages gold stock, while jualemas.id displays products and receives orders. Build a single local application that simulates a catalog, checkout, stock reservation, and payment confirmation. Use simulated data; there is no need to connect to company systems.

What is assessed is the accuracy of the transaction flow, implementation quality, testing, and the ability to understand and verify code produced with AI assistance.

---

## 02 / Work Requirements

- Work individually. Documentation, common packages, and official Laravel starters may be used; help from other people is not allowed. List any starter or additional code sources used.
- Prepare PHP, Composer, database, and AI tools before the session. Building features, seeders, tests, and documentation are all included within the 3-hour time limit. Record the start and finish time in the README.
- The start schedule, deadline, and submission channel are provided by HC (HR/People). If any part is unfinished, submit the last known state and explain the limitations.

---

## 03 / Initial Data

Provide a migration and seeder with the following data. All prices are for testing purposes only.

| Product | Unit Price | Stock |
|---|---|---|
| Antam 1 gram | Rp1,500,000 | 1 |
| UBS 1 gram | Rp1,450,000 | 3 |
| Emasku 0.5 gram | Rp750,000 | 0 |

### Scope Boundaries

No need for login, deployment, a real payment gateway, or a multi-product cart. Expiry and order cancellation only need to be explained as future development. Use a simple design that is readable on mobile phones.

---

## Transaction Features and Rules
### Mandatory implementation | Catalog, orders, and payment

## 04 / Catalog, Checkout, and Order Details

- Display product name, price, and stock. Customers can order one type of product with a quantity that is a positive integer. Reject invalid products and quantities exceeding stock.
- A successful order receives a unique number and a "pending" status. Available stock is reduced as a reservation when the order is created; a failed process must not leave behind an order or a partial stock reduction.
- Calculate the price and total on the server, then save the price at checkout time on the order. Ignore the price/total sent from the browser. Use whole rupiah values. Subsequent product price changes must not change orders that have already been created.
- Protect the last unit of stock from two simultaneous checkouts. The detail page displays the order number, product, quantity, price at checkout, total, and status, along with clear error messages.

## 05 / Payment Notification Simulation

Create a local endpoint `POST /api/mock-payments`. Use the `X-Payment-Token` header with a token sourced from the environment configuration. Include an example local value in `.env.example`.
An empty or incorrect token must be rejected.

```json
{
  "event_id": "evt-001",
  "order_number": "ORDER-001",
  "amount": 1500000,
  "status": "paid"
}
```

- Replace the example order number with the number produced by checkout. An order only becomes "paid" if the token, order, amount, and status are all valid. For this test, only the "paid" payment status is supported.
- The same event sent again must not cause any additional effect. A new event for an order that is already "paid" must also not reprocess the payment. An event ID that is reused with different data must be rejected.
- Stock must not be reduced again when payment is confirmed. The response must explain whether the payment was accepted, already processed, or rejected. Invalid requests must not change the order status.

## 06 / Minimum Automated Tests

Prove that:
1. A valid checkout creates an order and reduces stock.
2. Insufficient stock is rejected without any data change.
3. Request price manipulation has no effect.
4. A repeated payment event does not add any additional effect.
5. An incorrect token and an incorrect amount each do not change the status.

Explain the strategy for handling two simultaneous checkouts. If using SQLite, explain the limitations of concurrency testing and the verification plan for the production database.

---

## AI, Submission & Assessment
### Evidence of the working process and reproducible results

## 07 / AI Usage Documentation

Include an `AI_USAGE.md` file containing:

- The name of the tool/model, if known, and which parts of the work AI assisted with.
- Three important prompts along with a summary of the results and the decisions you made. There is no need to share the entire conversation history.
- Examples of AI suggestions that were changed or rejected, if any, along with the reasons. If there are none, explain how you verified the suggestions that were accepted.
- How you verified the AI's output: tests run, bugs found, fixes made, and parts you are still not sure are correct.

Use fictitious data. Do not include real credentials, customer data, or previous company code. You are responsible for explaining the code submitted, including AI-generated code.

## 08 / Deliverables

- Repository or ZIP of the code, including migrations, seeders, automated tests, and the dependency lock file. Do not include an `.env` containing secrets, `vendor`, or `node_modules`.
- README: version requirements, installation, local configuration, seeding commands, how to run the application and tests, time spent working, assumptions, and limitations.
- Example requests in cURL or Postman for successful, repeated, and rejected payments; include `AI_USAGE.md`.

**ZIP/Repository name:** `tes-skill-ai-nama-kandidat` (test-skill-ai-candidate-name)

Submit the link or files to HC through the provided channel. For private repositories, make sure the reviewer is given access through coordination with HC.

## 09 / Assessment Criteria

| Aspect | Weight |
|---|---|
| Accuracy of stock, price, and status changes | 35% |
| Validation, endpoint security, and failure handling | 20% |
| Testing that proves the application's behavior | 15% |
| Code readability and ease of running the application | 10% |
| Appearance and clarity of user feedback | 10% |
| Use of AI and ability to verify results | 10% |

**Main concerns:** negative stock/double-selling, price trusted from the browser, payment without a token, and repeated payment effects. The amount of code or the amount of AI usage does not add value on its own.

## 10 / Results Discussion (30 minutes)

- 10 minutes: application demo and design decisions.
- 10 minutes: explanation of the code, tests, and AI usage.
- 10 minutes: small changes or investigation of an additional case from the reviewer.

AI may still be used during this session; explain the reasoning for every change made.
