// BatStateU Portal Data Structure

const coursesData = [
  {
    id: 1,
    code: "CS101",
    name: "Introduction to Computer Science",
    professor: "Dr. Maria Santos",
    credits: 3,
    section: "A",
    status: "active",
    schedule: ["MWF 8:00-9:00", "TTh 8:00-9:30"],
    description: "Fundamentals of programming and computational thinking",
    color: "#3b82f6",
  },
  {
    id: 2,
    code: "CS201",
    name: "Data Structures",
    professor: "Prof. Juan Dela Cruz",
    credits: 4,
    section: "B",
    status: "active",
    schedule: ["MWF 10:00-11:00", "TTh 10:00-11:30"],
    description: "Study of arrays, linked lists, stacks, queues, trees",
    color: "#8b5cf6",
  },
  {
    id: 3,
    code: "MATH101",
    name: "Calculus I",
    professor: "Dr. Ana Garcia",
    credits: 3,
    section: "A",
    status: "active",
    schedule: ["MWF 14:00-15:00"],
    description: "Limits, derivatives, and applications of calculus",
    color: "#ec4899",
  },
  {
    id: 4,
    code: "ENG101",
    name: "English Composition",
    professor: "Prof. Rosa Mendoza",
    credits: 3,
    section: "C",
    status: "active",
    schedule: ["TTh 14:00-15:30"],
    description: "Academic writing and critical thinking",
    color: "#f59e0b",
  },
  {
    id: 5,
    code: "PHY101",
    name: "General Physics I",
    professor: "Dr. Carlos Reyes",
    credits: 4,
    section: "A",
    status: "active",
    schedule: ["MWF 16:00-17:00", "W 17:00-18:00 (Lab)"],
    description: "Mechanics, motion, forces, energy, and waves",
    color: "#06b6d4",
  },
]

// Sample Assignments Data
const assignmentsData = [
  {
    id: 1,
    title: "Problem Set 1",
    course: "CS101",
    description: "Complete exercises 1-10 from Chapter 1. Focus on algorithm design and pseudocode.",
    dueDate: "2025-11-12",
    status: "pending",
    points: 50,
    submittedDate: null,
    grade: null,
    rubric: "Correctness (20pts), Code Quality (15pts), Documentation (15pts)",
  },
  {
    id: 2,
    title: "Lab Exercise 3",
    course: "CS201",
    description: "Implement binary search algorithm with detailed comments. Test with at least 5 test cases.",
    dueDate: "2025-11-10",
    status: "submitted",
    points: 100,
    submittedDate: "2025-11-09",
    grade: null,
    rubric: "Implementation (50pts), Testing (30pts), Report (20pts)",
  },
  {
    id: 3,
    title: "Calculus Problem Set",
    course: "MATH101",
    description: "Solve derivative and integration problems. Show all work step by step.",
    dueDate: "2025-11-08",
    status: "graded",
    points: 50,
    submittedDate: "2025-11-07",
    grade: 45,
    rubric: "Accuracy (25pts), Method (15pts), Presentation (10pts)",
  },
  {
    id: 4,
    title: "Essay - Digital Transformation in Business",
    course: "ENG101",
    description: "Write 1500-2000 word essay on digital transformation. Include at least 5 academic sources.",
    dueDate: "2025-11-15",
    status: "pending",
    points: 100,
    submittedDate: null,
    grade: null,
    rubric: "Content (40pts), Organization (30pts), Grammar (20pts), Citations (10pts)",
  },
  {
    id: 5,
    title: "Physics Lab Report",
    course: "PHY101",
    description: "Complete lab report on simple harmonic motion. Include data analysis and graphs.",
    dueDate: "2025-11-14",
    status: "pending",
    points: 75,
    submittedDate: null,
    grade: null,
    rubric: "Data Collection (20pts), Analysis (30pts), Presentation (15pts), Conclusion (10pts)",
  },
  {
    id: 6,
    title: "Code Review Project",
    course: "CS101",
    description: "Review peer's code and provide constructive feedback on design and efficiency.",
    dueDate: "2025-11-09",
    status: "graded",
    points: 30,
    submittedDate: "2025-11-08",
    grade: 28,
    rubric: "Feedback Quality (20pts), Timeliness (5pts), Helpfulness (5pts)",
  },
]

// Sample Grades Data
const gradesData = [
  {
    courseCode: "CS101",
    courseName: "Introduction to Computer Science",
    professor: "Dr. Maria Santos",
    midterm: 88,
    finals: null,
    assignments: 90,
    participation: 85,
    average: 87.67,
  },
  {
    courseCode: "CS201",
    courseName: "Data Structures",
    professor: "Prof. Juan Dela Cruz",
    midterm: 85,
    finals: null,
    assignments: 88,
    participation: 90,
    average: 87.67,
  },
  {
    courseCode: "MATH101",
    courseName: "Calculus I",
    professor: "Dr. Ana Garcia",
    midterm: 82,
    finals: null,
    assignments: 85,
    participation: 80,
    average: 82.33,
  },
  {
    courseCode: "ENG101",
    courseName: "English Composition",
    professor: "Prof. Rosa Mendoza",
    midterm: 90,
    finals: null,
    assignments: 92,
    participation: 88,
    average: 90,
  },
  {
    courseCode: "PHY101",
    courseName: "General Physics I",
    professor: "Dr. Carlos Reyes",
    midterm: 86,
    finals: null,
    assignments: 84,
    participation: 87,
    average: 85.67,
  },
]

// Sample Announcements Data
const announcementsData = [
  {
    id: 1,
    title: "System Maintenance - November 15",
    content:
      "The university student portal will undergo scheduled maintenance on November 15, 2025 from 2:00 AM to 6:00 AM. Some services may be temporarily unavailable during this period.",
    type: "university",
    date: "2025-11-05",
    priority: "high",
    department: "IT Services",
  },
  {
    id: 2,
    title: "Midterm Examination Schedule Released",
    content:
      "Midterm examination schedules for all courses have been released and are now available on the portal. Please check your course pages for specific dates, times, and room assignments.",
    type: "course",
    date: "2025-11-04",
    priority: "high",
    department: "Academic Affairs",
  },
  {
    id: 3,
    title: "CS101 - Class Rescheduled",
    content:
      "Due to an emergency, tomorrow's CS101 class (Wednesday) is rescheduled to Friday at 4:00 PM in Lab Building 301. Please make note of this change.",
    type: "course",
    date: "2025-11-03",
    priority: "medium",
    department: "Computer Science Department",
  },
  {
    id: 4,
    title: "Library Extended Hours During Exam Season",
    content:
      "During exam season, the university library will remain open until 11:00 PM on weekdays and 8:00 PM on weekends to support student study needs.",
    type: "university",
    date: "2025-11-02",
    priority: "low",
    department: "Library Services",
  },
  {
    id: 5,
    title: "Scholarship Applications Now Open",
    content:
      "Applications for academic scholarships are now open. The deadline is November 30, 2025. Visit the Student Financial Aid office or the portal for more information and application materials.",
    type: "university",
    date: "2025-11-01",
    priority: "medium",
    department: "Student Services",
  },
  {
    id: 6,
    title: "Important: Assignment Submission Guidelines",
    content:
      "All assignments must be submitted before 11:59 PM on the due date. Late submissions will incur a 10% penalty per day. Submit your work through the portal only.",
    type: "course",
    date: "2025-10-31",
    priority: "high",
    department: "Academic Affairs",
  },
]

// Sample Schedule Data
const scheduleData = [
  { course: "CS101", time: "08:00-09:00", day: "Monday", room: "Lab Building 301", instructor: "Dr. Maria Santos" },
  { course: "MATH101", time: "10:00-11:00", day: "Monday", room: "Science Hall 205", instructor: "Dr. Ana Garcia" },
  { course: "CS201", time: "14:00-15:30", day: "Monday", room: "Lab Building 302", instructor: "Prof. Juan Dela Cruz" },
  { course: "CS101", time: "08:00-09:00", day: "Wednesday", room: "Lab Building 301", instructor: "Dr. Maria Santos" },
  {
    course: "ENG101",
    time: "14:00-15:30",
    day: "Wednesday",
    room: "Arts Building 101",
    instructor: "Prof. Rosa Mendoza",
  },
  { course: "CS101", time: "08:00-09:00", day: "Friday", room: "Lab Building 301", instructor: "Dr. Maria Santos" },
  { course: "MATH101", time: "10:00-11:00", day: "Friday", room: "Science Hall 205", instructor: "Dr. Ana Garcia" },
  { course: "CS201", time: "14:00-15:30", day: "Friday", room: "Lab Building 302", instructor: "Prof. Juan Dela Cruz" },
  {
    course: "CS201",
    time: "10:00-11:30",
    day: "Tuesday",
    room: "Lab Building 302",
    instructor: "Prof. Juan Dela Cruz",
  },
  {
    course: "ENG101",
    time: "10:00-11:30",
    day: "Tuesday",
    room: "Arts Building 101",
    instructor: "Prof. Rosa Mendoza",
  },
  { course: "MATH101", time: "14:00-15:00", day: "Thursday", room: "Science Hall 205", instructor: "Dr. Ana Garcia" },
  {
    course: "CS201",
    time: "10:00-11:30",
    day: "Thursday",
    room: "Lab Building 302",
    instructor: "Prof. Juan Dela Cruz",
  },
  { course: "PHY101", time: "16:00-17:00", day: "Monday", room: "Science Hall 101", instructor: "Dr. Carlos Reyes" },
  {
    course: "PHY101",
    time: "17:00-18:00",
    day: "Wednesday",
    room: "Science Hall 101 (Lab)",
    instructor: "Dr. Carlos Reyes",
  },
]

// Sample Messages Data
const messagesData = [
  {
    id: 1,
    sender: "Dr. Maria Santos",
    course: "CS101",
    subject: "Project Feedback - Excellent Work!",
    preview:
      "Your project submission looks great. The algorithm design is clear and efficient. Please check my detailed feedback in the course portal.",
    date: "2025-11-05 14:30",
    read: false,
    fullMessage:
      "Your project submission looks great. The algorithm design is clear and efficient. I particularly liked your approach to optimization. Please check my detailed feedback in the course portal. Keep up the good work!",
  },
  {
    id: 2,
    sender: "Prof. Juan Dela Cruz",
    course: "CS201",
    subject: "Re: Data Structures Question",
    preview:
      "Regarding your question about linked lists, you can find the solution in Chapter 5, Section 3 of the textbook.",
    date: "2025-11-04 10:15",
    read: true,
    fullMessage:
      "Regarding your question about linked lists, you can find the solution in Chapter 5, Section 3 of the textbook. Additionally, I recommend reviewing the sample code provided in the course materials. Feel free to ask if you have more questions.",
  },
  {
    id: 3,
    sender: "Dean's Office",
    course: "General",
    subject: "Scholarship Opportunity - Apply Now",
    preview:
      "We are pleased to inform you about new scholarship opportunities available for deserving students. Deadline: November 30, 2025.",
    date: "2025-11-03 09:00",
    read: false,
    fullMessage:
      "We are pleased to inform you about new scholarship opportunities available for deserving students. You are invited to apply based on your academic performance. Deadline: November 30, 2025. Contact Student Financial Aid for more details.",
  },
  {
    id: 4,
    sender: "Dr. Ana Garcia",
    course: "MATH101",
    subject: "Exam Preparation Guidance",
    preview:
      "For the upcoming midterm, I recommend reviewing chapters 1-5 thoroughly. Focus on derivatives and integration problems.",
    date: "2025-11-02 16:45",
    read: true,
    fullMessage:
      "For the upcoming midterm, I recommend reviewing chapters 1-5 thoroughly. Focus on derivatives and integration problems. Practice with old exams available on the portal. Office hours available Monday-Friday 2-4 PM.",
  },
  {
    id: 5,
    sender: "Prof. Rosa Mendoza",
    course: "ENG101",
    subject: "Essay Submission Tips",
    preview:
      "For your upcoming essay, remember to include a clear thesis statement and support it with evidence from your research.",
    date: "2025-11-01 11:20",
    read: false,
    fullMessage:
      "For your upcoming essay, remember to include a clear thesis statement and support it with evidence from your research. Use at least 5 academic sources. Check the rubric carefully before submission.",
  },
]

// Calculate GPA
function calculateGPA() {
  if (gradesData.length === 0) return "0.00"
  const total = gradesData.reduce((sum, grade) => sum + grade.average, 0)
  return (total / gradesData.length).toFixed(2)
}
