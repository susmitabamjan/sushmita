import csv
import os

FILE_NAME = "students.csv"

def add_student():
    with open(FILE_NAME, "a", newline="") as file:
        name = input("Enter student name: ")
        age = input("Enter student age: ")
        grade = input("Enter student grade: ")
        writer = csv.writer(file)

        if os.path.getsize(FILE_NAME)==0:
            writer.writerow(["Name", "Age", "Grade"])

        writer.writerow([name, age, grade])


def show_students():
    with open(FILE_NAME, "r") as file:
        reader = list(csv.reader(file))
        print(f"SN.Name, Age, Grade")
        reader = reader[1:]
        for i, std in enumerate(reader):
            # print(f"{i+1}.Name:{std[0]}, Age:{std[1]}, Grade:{std[2]}")
            print(f"{i+1}.{std[0]}, {std[1]}, {std[2]}")


def search_student():
    name = input("Enter student name: ")
    global result
    result = []
    with open(FILE_NAME, 'r') as file:
        reader = list(csv.reader(file)) #[[],[],[]]
        for row in reader:
            if row[0] == name:
                result = row
                break
        if len(result)>0:
            print(f"Name: {result[0]}")
            print(f"Age: {result[1]}")
            print(f"Grade: {result[2]}")
        else:
            print("Data not found")

def delete_student():
    name = input("Enter student name: ")
    global result
    result = []
    with open(FILE_NAME, 'r') as file:
        reader = list(csv.reader(file)) #[[],[],[]]
        for row in reader:
            if row[0] == name:
                result = row
                break   
    if len(result)>0:
        reader.remove(result)
        with open(FILE_NAME, 'w', newline="") as file:
            writer = csv.writer(file)
            writer.writerows(reader)
    else:
        print("Student not found!")


def update_student():
    name = input("Enter student name: ")
    global result
    result = []
    with open(FILE_NAME, 'r') as file:
        reader = list(csv.reader(file)) #[[],[],[]]
        for row in reader:
            if row[0] == name:
                result = row
                break
    if len(result)>0:
        newname = input("Enter student updated name: ")
        age = input("Enter student updated age: ")
        grade = input("Enter student updated grade: ")
        index = reader.index(result)
        reader.remove(result)
        reader.insert(index, [newname,age,grade])
        with open(FILE_NAME, 'w', newline="") as file:
            writer = csv.writer(file)
            writer.writerows(reader)
    else:
        print("Student not found!")

MENU = """
1. Add Student
2. Show all Students
3. Delete Student by name
4. Search Student by name
5. Update Student
6. Exit
"""

while True:
    print(MENU)
    user_choice = input("Enter your choice(1-6): ")
    if user_choice == "1":
        add_student()
    elif user_choice == "2":
        show_students()
    elif user_choice == "3":
        delete_student()
    elif user_choice == "4":
        search_student()
    elif user_choice == "5":
        update_student()
    elif user_choice == "6":
        break
    else:
        print("Invalid user choice!")
