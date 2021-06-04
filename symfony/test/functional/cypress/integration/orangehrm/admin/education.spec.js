import user from '../../../fixtures/user.json'
import promisify from 'cypress-promise'



//Get the current education list count
const getEducationListCount = async() =>{ 
    let txt = await promisify(cy.get('.oxd-text').contains('Found').invoke('text'))
    return parseInt(txt.split(' ')[0])
}

describe('Qualification - Education', function () {

    const characterLengthof51 = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
    const educationName = "Test12"
    const updatedEducationName = "Test123"

   
//View Education List Page
    it('Check education view page', () => {
        cy.login(user.admin.userName, user.admin.password)

        cy.visit('/admin/viewEducation')

        cy.get('.oxd-text--h6').should('include.text', "Education")
       
      
    }) 



//Add Education record to the list
//check the Successfully Added toast message 
//Check whether count of the list has increased by 1 after addding a new record to the list

    it('Add education',async () =>{
        cy.login(user.admin.userName, user.admin.password)
        cy.visit('/admin/viewEducation')
        cy.wait(2000)


        const previousCount = await getEducationListCount()
        cy.log(previousCount)


        cy.get('.oxd-text--span').should('include.text', "Found")
        cy.get('.oxd-button--medium').should('include.text',"Add").click()
        cy.get('.oxd-input-group').type(educationName)
        cy.get('.oxd-button--secondary').click()
        cy.wait(500)
        cy.get('.oxd-toast').should('include.text', 'Success')

        cy.wait(7000)
        const updatedCount = await getEducationListCount()

        cy.wait(7000)
      
        expect(updatedCount).to.eq(previousCount+1)

    })



//Validate required fields in Save Education Screen

    it('Required field validation',() =>{
        cy.login(user.admin.userName, user.admin.password)
        cy.visit('/admin/viewEducation')
        cy.get('.oxd-button--medium').should('include.text',"Add").click()
        cy.get('.oxd-button--secondary').click()
        cy.get('.oxd-input-group__message').should('include.text', 'Required')

    })


//Validate maximum character length of the fields in Save Education screen

    it('Maximum Length validation',() =>{
        cy.login(user.admin.userName, user.admin.password)
        cy.visit('/admin/viewEducation')
        cy.get('.oxd-button--medium').should('include.text',"Add").click()
        cy.get('.oxd-button--secondary').click()
        cy.get('.oxd-input-group').type(characterLengthof51)
        cy.get('.oxd-input-group__message').should('include.text', 'Should not exceed 50 characters')
        cy.get('.oxd-button--secondary').click()
        cy.url().should('include', '/admin/saveEducation')

    })




//Validation check for the duplicated records

    it('Duplicate Records Validation',() =>{
        cy.login(user.admin.userName, user.admin.password)
        
        cy.login(user.admin.userName, user.admin.password)
        cy.visit('/admin/viewEducation')
        cy.get('.oxd-button--medium').should('include.text',"Add").click()
        cy.get('.oxd-input-group').type(educationName)
        cy.get('.oxd-button--secondary').click()
        cy.get('.oxd-toast').should('include.text', 'Success')

        cy.wait(2000)
        cy.get('.oxd-button--medium').should('include.text',"Add").click()
        cy.get('.oxd-input-group').type(educationName)
        cy.get('.oxd-button--secondary').click()
        cy.get('.oxd-input-group__message').should('include.text', 'Qualification name should be unique')
        cy.get('.oxd-button--secondary').click()
        cy.url().should('include', '/admin/saveEducation')

    })



//Update an existing education record from the list
//check the Updated toast message 
//Check whether count of the list remains the same after updating 
    
        it('Edit education', async () =>{
            cy.login(user.admin.userName, user.admin.password)
            cy.visit('/admin/viewEducation')
            cy.wait(2000)
    
    
            const previousCount = await getEducationListCount()
            cy.log(previousCount)
    
    
            cy.get('.bi-pencil-fill').first().click()
            cy.get('.oxd-input-group').type(updatedEducationName)
            cy.get('.oxd-button--secondary').click()
            cy.wait(500)
        
            cy.get('.oxd-toast').should('include.text', 'Updated')
    
            cy.wait(7000)
            const updatedCount = await getEducationListCount()
    
            cy.wait(7000)
          
            expect(updatedCount).to.eq(previousCount)

    })

    
    
//Delete an Education record from the list
//check the Deleted toast message 
//Check whether count of the list has reduced by 1 after deleting a record from the list

    it('Delete education', async () =>{
        cy.login(user.admin.userName, user.admin.password)
        cy.visit('/admin/viewEducation')
        cy.wait(2000)


        const previousCount = await getEducationListCount()
        cy.log(previousCount)


        cy.get('.bi-trash').first().click()
        cy.wait(500)
        cy.get('.oxd-button--label-danger').click()
        cy.wait(500)
    
        cy.get('.oxd-toast').should('include.text', 'Deleted')

        cy.wait(7000)
        const updatedCount = await getEducationListCount()

        cy.wait(7000)
      
        expect(updatedCount).to.eq(previousCount-1)

})


})
