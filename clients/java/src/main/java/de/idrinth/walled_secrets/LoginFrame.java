package de.idrinth.walled_secrets;

import javax.swing.JFrame;

public class LoginFrame extends JFrame
{
    private final SwingFactory factory;
    public LoginFrame(String project, SwingFactory factory, Config config)
    {
        super("Login | " + project);
        this.factory = factory;
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        add(new LoginPanel(factory, config));
        pack();
    }
}
