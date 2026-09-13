import sys

# CRUCIAL for this terminal: force line-buffering so prompts appear 
# instantly instead of being held until the program finishes.
sys.stdout.reconfigure(line_buffering=True)

name = input("Enter your name: ")
age = input("Enter your age: ")

print(f"\nHello! You are {name} and {age} years old, right?")
print("This is a Python code and it's working perfectly!")