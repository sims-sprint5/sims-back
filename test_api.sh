#!/bin/bash

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
NC='\033[0m'

API="http://localhost:8000/api"

echo -e "${BLUE}=== TEST 1: Login SuperAdmin ===${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$API/v1/superadmin/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@sims.com","password":"ikeradmin2026"}')

echo "$LOGIN_RESPONSE" | jq '.'

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token')

if [ -z "$TOKEN" ] || [ "$TOKEN" = "null" ]; then
  echo -e "${RED}❌ Login failed!${NC}"
  exit 1
fi

echo -e "${GREEN}✅ Token: ${TOKEN:0:30}...${NC}\n"

echo -e "${BLUE}=== TEST 2: Create Tenant ===${NC}"
CREATE_RESPONSE=$(curl -s -X POST "$API/v1/superadmin/tenants" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "id":"testtenant001",
    "name":"Test Tenant 001",
    "admin_name":"Test Admin",
    "admin_email":"admin@testtenant001.com",
    "admin_password":"testpass123"
  }')

echo "$CREATE_RESPONSE" | jq '.'

echo -e "\n${BLUE}=== TEST 3: Update Tenant ===${NC}"
UPDATE_RESPONSE=$(curl -s -X PUT "$API/v1/superadmin/tenants/testtenant001" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name":"Test Tenant 001 - Updated",
    "admin_name":"Updated Admin",
    "admin_email":"updated@testtenant001.com"
  }')

echo "$UPDATE_RESPONSE" | jq '.'

echo -e "\n${BLUE}=== TEST 4: Get Updated Tenant ===${NC}"
GET_RESPONSE=$(curl -s -X GET "$API/v1/superadmin/tenants/testtenant001" \
  -H "Authorization: Bearer $TOKEN")

echo "$GET_RESPONSE" | jq '.'

echo -e "\n${GREEN}✅ Tests completed!${NC}"
