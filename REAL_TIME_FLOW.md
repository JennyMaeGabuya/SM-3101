# SANROOM Real-Time Participant Tracking

## How It Works

### Student Side (students.php)

- Student enters a **join code** and clicks "Join Class"
- System calls `enroll_student.php` which:
  1. Validates the student session ID
  2. Looks up schedule by join_code
  3. Inserts record into `schedule_participants` table
  4. Sets `localStorage.schedulesUpdated` to trigger refresh
  5. Returns success message
- Student can join multiple times (each join is idempotent)

### Teacher Side (rooms.php - Room Management)

- Page loads room data from `get_all_rooms.php` API
- API returns each room with `current_participants` count
- Display shows: **Capacity | Students | Available**
- JavaScript automatically polls every 2 seconds for updates
- When student joins on student page, localStorage event fires
- Room page detects event and immediately reloads

## Data Flow

```
Student joins class
  ↓
enroll_student.php inserts into schedule_participants
  ↓
localStorage.setItem("schedulesUpdated", timestamp)
  ↓
roomManagement.js listener detects storage change
  ↓
loadRooms() function fetches from get_all_rooms.php
  ↓
get_all_rooms.php queries:
  SELECT rooms with COUNT(DISTINCT students)
  FROM schedule_participants
  INNER JOIN schedules
  ↓
Frontend displays updated participant count
```

## Files Modified

### Backend

- `public/backend/enroll_student.php` - Student join endpoint
- `public/backend/get_all_rooms.php` - Room data with participant counts
- `database.php` - MySQL connection (existing)

### Frontend JavaScript

- `public/assets/js/student_dash.js` - Student join form
- `public/assets/js/roomManagement.js` - Room display with polling (2-second interval)

### Database Tables

- `schedule_participants` - Stores student-schedule mappings
- `schedules` - Has room column linking to rooms
- `rooms` - Room data with capacity

## Testing

### Quick Test

Visit: `http://localhost/SANROOM/quick_test.php`

### Manual Test Steps

1. Open `http://localhost/SANROOM/public/students.php` in Tab A
2. Open `http://localhost/SANROOM/public/rooms.php` in Tab B
3. In Tab A: Enter a join code and click "Join Class"
4. Watch Tab B: "Students" count should increase within 2 seconds
5. Check browser console (F12) for debug logs

### Debug Logs

- `enroll_student.php` logs to PHP error log
- `roomManagement.js` logs to browser console
- Database queries logged when errors occur

## Key Features

✓ Real-time updates (2-second polling)
✓ Multiple joins supported (idempotent)
✓ Works across page/tab boundaries
✓ Fallback polling (doesn't require cross-tab communication)
✓ Shows remaining capacity calculations
✓ Responsive error handling
