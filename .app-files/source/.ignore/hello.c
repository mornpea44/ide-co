#include <stdio.h>

int main() {
    /* CRUCIAL for this terminal: turn OFF stdout buffering so prompts
       appear immediately even though output goes through a pipe. */
    setvbuf(stdout, NULL, _IONBF, 0);

    char name[50];
    int age;

    printf("Enter your name: ");
    scanf("%49s", name);

    printf("\nEnter your age: ");
    scanf("%d", &age);

    printf("\nHello! You are %s and %d years old, right?\n", name, age);
    printf("This is a C code and it's working perfectly!\n");

    return 0;
}